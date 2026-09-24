<?php

namespace App\Models;

use App\Support\SqlLike;
use App\Support\UsStates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A placed order. Written only by App\Services\Commerce\CheckoutService, which
 * is where the totals are computed - nothing here recalculates money, it only
 * formats what was stored.
 */
class Order extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    protected $table = 'tbl_orders';

    /**
     * order_number, public_token and every total are excluded: they are set by
     * the checkout service from server-side figures and must not be settable
     * from a request.
     */
    protected $fillable = [
        'status',
        'payment_status',
        'first_name',
        'last_name',
        'email',
        'phone',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'coupon_id' => 'integer',
            'placed_at' => 'datetime',
            'stock_restored_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------ relations */

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(OrderPayment::class, 'order_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    /**
     * Where the order came from. Absent on anything placed before attribution
     * was recorded, so every caller has to cope with null.
     */
    public function attribution(): HasOne
    {
        return $this->hasOne(OrderAttribution::class, 'order_id');
    }

    /* --------------------------------------------------------------- routing */

    /**
     * The confirmation page is reached by the unguessable token, never by id.
     */
    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    /* -------------------------------------------------------------- helpers */

    public function customerName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * The billing address on one line, for a table cell.
     */
    public function addressLine(): string
    {
        return implode(', ', array_filter([
            $this->street,
            $this->city,
            $this->state.' '.$this->postal_code,
            $this->country,
        ]));
    }

    public function stateName(): string
    {
        return UsStates::name($this->state) ?? $this->state;
    }

    public function money(?float $amount): string
    {
        $symbol = match ($this->currency) {
            'USD', 'AUD', 'CAD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            default => $this->currency.' ',
        };

        return $symbol.number_format((float) $amount, 2);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * A delivered order is finished; the panel offers nothing but cancel.
     */
    public function isClosed(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * The colour the panel paints the status badge. Kept beside the statuses
     * themselves so a new one cannot be added without deciding how it looks.
     */
    public function statusBadge(): string
    {
        return match ($this->status) {
            'delivered' => 'badge-active',
            'shipped' => 'badge-info',
            'processing' => 'badge-warn',
            'cancelled' => 'badge-danger',
            default => 'badge-muted',
        };
    }

    public function paymentBadge(): string
    {
        return match ($this->payment_status) {
            'paid' => 'badge-active',
            'failed' => 'badge-danger',
            'refunded' => 'badge-warn',
            default => 'badge-muted',
        };
    }

    /* --------------------------------------------------------------- scopes */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = SqlLike::contains($term);

        return $query->where(function (Builder $q) use ($like) {
            $q->where('order_number', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like);
        });
    }
}
