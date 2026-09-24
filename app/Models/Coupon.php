<?php

namespace App\Models;

use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A discount code.
 *
 * The model owns the arithmetic and the eligibility rules, so the cart, the
 * checkout and the panel all get the same answer from one implementation.
 */
class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['percent', 'fixed'];

    protected $table = 'tbl_coupons';

    /**
     * used_count is excluded on purpose: it is incremented by the checkout as
     * orders are placed and must never be settable from a form post.
     */
    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'starts_at',
        'ends_at',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------ relations */

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'coupon_id');
    }

    /* ------------------------------------------------------------- mutators */

    /**
     * Codes are compared case-insensitively everywhere, so they are stored in
     * one case rather than lower-cased at each call site.
     */
    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = mb_strtoupper(trim((string) $value));
    }

    /* -------------------------------------------------------------- rules */

    /**
     * Live and inside its date window, with redemptions left. Says nothing
     * about any particular basket - that is qualifies().
     */
    public function isRedeemable(): bool
    {
        return $this->is_active
            && ! $this->hasNotStarted()
            && ! $this->hasExpired()
            && ! $this->isExhausted();
    }

    public function hasNotStarted(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isFuture();
    }

    public function hasExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    /**
     * The minimum-spend condition. A subtotal exactly on the line qualifies -
     * "$99 minimum" reads as "$99 is enough", and anything else generates
     * support tickets.
     */
    public function qualifies(float $subtotal): bool
    {
        return $subtotal >= (float) $this->min_order_amount;
    }

    /**
     * How much this code takes off a basket, in currency.
     *
     * Returns 0 rather than throwing when the code does not apply, so a page
     * repainting after a quantity change never has to guard the call. The
     * discount can never exceed the subtotal: an order that pays the customer
     * is not a discount.
     */
    public function discountFor(float $subtotal): float
    {
        if (! $this->isRedeemable() || ! $this->qualifies($subtotal) || $subtotal <= 0) {
            return 0.0;
        }

        $off = $this->type === 'percent'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount_amount !== null) {
            $off = min($off, (float) $this->max_discount_amount);
        }

        return round(min($off, $subtotal), 2);
    }

    /**
     * How much more has to go in the basket before the code works, or 0 when
     * it already does.
     */
    public function shortfallFor(float $subtotal): float
    {
        return round(max(0, (float) $this->min_order_amount - $subtotal), 2);
    }

    /* -------------------------------------------------------------- labels */

    /**
     * "10% off" / "$15 off" - the headline on the cart card.
     */
    public function valueLabel(): string
    {
        if ($this->type === 'percent') {
            return rtrim(rtrim(number_format((float) $this->value, 2, '.', ''), '0'), '.').'% off';
        }

        return config('shop.currency_symbol', '$').number_format((float) $this->value, 2).' off';
    }

    /**
     * The line under the headline. The operator's own words when they wrote
     * some, otherwise one derived from the rule.
     */
    public function descriptionLabel(): string
    {
        if (filled($this->description)) {
            return $this->description;
        }

        return (float) $this->min_order_amount > 0
            ? 'On orders over '.config('shop.currency_symbol', '$').number_format((float) $this->min_order_amount, 2)
            : 'On any order';
    }

    /**
     * The complete customer-facing line used by a compact promotion banner.
     */
    public function promotionLabel(): string
    {
        if (filled($this->description)) {
            return $this->description;
        }

        return $this->valueLabel().' — '.$this->descriptionLabel();
    }

    /**
     * Why a code is not usable right now, for the panel list. Null when it is
     * perfectly fine.
     */
    public function unusableReason(): ?string
    {
        return match (true) {
            ! $this->is_active => 'Disabled',
            $this->hasNotStarted() => 'Scheduled',
            $this->hasExpired() => 'Expired',
            $this->isExhausted() => 'Used up',
            default => null,
        };
    }

    public function statusLabel(): string
    {
        return $this->unusableReason() ?? 'Active';
    }

    /* --------------------------------------------------------------- scopes */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Active, in date, and with redemptions left - the set the storefront is
     * allowed to offer.
     */
    public function scopeRedeemable(Builder $query): Builder
    {
        return $query->active()
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn (Builder $q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = SqlLike::contains($term);

        return $query->where(function (Builder $q) use ($like) {
            $q->where('code', 'like', $like)->orWhere('description', 'like', $like);
        });
    }
}
