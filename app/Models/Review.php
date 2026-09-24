<?php

namespace App\Models;

use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One customer review.
 *
 * Written by App\Services\Reviews\ReviewService, which is also what keeps the
 * denormalised rating on the product in step. Nothing else should write to
 * this table or the two numbers will drift apart.
 */
class Review extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const CATEGORIES = [
        'quality' => 'Product quality',
        'delivery' => 'Delivery',
        'packaging' => 'Packaging',
        'support' => 'Customer service',
        'value' => 'Pricing and value',
    ];

    protected $table = 'tbl_reviews';

    /**
     * status, approved_at, approved_by and helpful_count are excluded: they
     * are moderation state, owned by the service, and must not be settable
     * from a form a stranger fills in.
     */
    protected $fillable = [
        'product_id',
        'order_id',
        'author_name',
        'author_email',
        'location',
        'category',
        'rating',
        'title',
        'body',
        'is_featured',
        'is_verified',
        'source',
        'ip_address',
    ];

    /**
     * The reviewer's address is never public. Hidden so a stray toArray()
     * cannot put it in a response.
     */
    protected $hidden = ['author_email', 'ip_address'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'helpful_count' => 'integer',
            'is_featured' => 'boolean',
            'is_verified' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------ relations */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /* -------------------------------------------------------------- reading */

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * "★★★★☆" - the same five-character string the product cards print.
     */
    public function stars(): string
    {
        $filled = max(0, min(5, $this->rating));

        return str_repeat('★', $filled).str_repeat('☆', 5 - $filled);
    }

    /**
     * The initials in the avatar circle.
     */
    public function initials(): string
    {
        return Str::of($this->author_name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }

    /**
     * "Verified customer · Denver", or as much of it as is true.
     */
    public function byline(): string
    {
        return implode(' · ', array_filter([
            $this->is_verified ? 'Verified customer' : 'Customer',
            $this->location,
        ]));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? Str::title($this->category);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'approved' => 'badge-active',
            'rejected' => 'badge-danger',
            default => 'badge-warn',
        };
    }

    /* --------------------------------------------------------------- scopes */

    /**
     * What a visitor is allowed to see. Everything the storefront reads goes
     * through this - a review is private until an operator publishes it.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('approved_at')->orderByDesc('id');
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Reviews about the shop rather than about one product - what the reviews
     * page leads with.
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereNull('product_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = SqlLike::contains($term);

        return $query->where(function (Builder $q) use ($like) {
            $q->where('author_name', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('body', 'like', $like);
        });
    }
}
