<?php

namespace App\Models;

use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'archived'];

    protected $table = 'tbl_products';

    /**
     * slug, thumbnail_path, rating_avg and rating_count are all excluded: they
     * are derived or owned by another module, so mass assignment must not be
     * able to set them from a form post.
     */
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'brand',
        'short_description',
        'description',
        'price',
        'compare_at_price',
        'cost_price',
        'currency',
        'unit',
        'weight_grams',
        'track_inventory',
        'stock_quantity',
        'low_stock_threshold',
        'allow_backorder',
        'status',
        'is_featured',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            // decimal:2 keeps these as fixed-point strings all the way to the
            // response, so no float rounding creeps into a displayed price.
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight_grams' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'track_inventory' => 'boolean',
            'allow_backorder' => 'boolean',
            'is_featured' => 'boolean',
            // Cast so a multipart form post, where every value arrives as a
            // string, does not report category_id as "1" in the API response.
            'category_id' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'rating_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------ relations */

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(ProductDetail::class, 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }

    /**
     * Selectable sizes. Optional: a product with none is sold as a single item
     * at its own price, and the size chooser is not rendered at all.
     */
    public function packs(): HasMany
    {
        return $this->hasMany(ProductPack::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /* -------------------------------------------------------------- helpers */

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? asset($this->thumbnail_path) : null;
    }

    /**
     * Out of stock only counts when inventory is actually tracked, and a
     * backorder-enabled product is never "out".
     */
    public function isOutOfStock(): bool
    {
        return $this->track_inventory
            && ! $this->allow_backorder
            && $this->stock_quantity <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory
            && $this->stock_quantity > 0
            && $this->stock_quantity <= $this->low_stock_threshold;
    }

    /**
     * Percentage off, for the storefront badge. Only meaningful when the
     * compare-at price is genuinely higher, so a mis-keyed value shows nothing
     * rather than a negative discount.
     */
    public function discountPercent(): ?int
    {
        $compare = (float) $this->compare_at_price;
        $price = (float) $this->price;

        if ($compare <= 0 || $compare <= $price) {
            return null;
        }

        return (int) round((($compare - $price) / $compare) * 100);
    }

    /* ---------------------------------------------------------- storefront */

    /**
     * Price with its currency symbol, e.g. "$38.00".
     *
     * The storefront markup was written with a hard-coded "$", so this keeps
     * that reading for USD while still being correct if a product is priced in
     * something else. An unknown code falls back to the code itself rather than
     * guessing a symbol.
     */
    public function priceFormatted(?float $amount = null): string
    {
        return $this->currencySymbol().number_format($amount ?? (float) $this->price, 2);
    }

    /**
     * An unknown code falls back to the code itself rather than guessing a
     * symbol - "CHF 12.00" is honest, a wrong symbol is not.
     */
    public function currencySymbol(): string
    {
        return match ($this->currency) {
            'USD', 'AUD', 'CAD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            default => $this->currency.' ',
        };
    }

    public function compareAtFormatted(): ?string
    {
        return $this->compare_at_price !== null
            ? $this->priceFormatted((float) $this->compare_at_price)
            : null;
    }

    /**
     * The five-character star string the product cards print, e.g. "★★★★☆".
     * Rounded to the nearest whole star, which is what the original markup did.
     */
    public function ratingStars(): string
    {
        $full = (int) round((float) $this->rating_avg);
        $full = max(0, min(5, $full));

        return str_repeat('★', $full).str_repeat('☆', 5 - $full);
    }

    /**
     * The corner badge on a card, or null for no badge. Derived from real
     * columns rather than decided per card, so the storefront cannot claim a
     * product is new once it is a year old.
     *
     * @return array{label: string, gold: bool}|null
     */
    public function badge(): ?array
    {
        if ($this->is_featured) {
            return ['label' => 'Top rated', 'gold' => true];
        }

        $published = $this->published_at ?? $this->created_at;

        if ($published !== null && $published->greaterThan(now()->subDays(30))) {
            return ['label' => 'New', 'gold' => false];
        }

        return null;
    }

    /**
     * How full the stock bar on the shop card is drawn, 0-100.
     *
     * The bar needs a "full" reference and the schema has no maximum stock, so
     * it is measured against four times the low-stock threshold. That is not
     * arbitrary: it makes the bar cross 25% exactly as the product reaches its
     * own low-stock line, which is what the three labels below key off.
     */
    public function stockPercent(): int
    {
        if (! $this->track_inventory) {
            return 100;
        }

        $ceiling = max(($this->low_stock_threshold ?: 5) * 4, 1);

        return (int) min(100, max(0, round(($this->stock_quantity / $ceiling) * 100)));
    }

    /**
     * The words beside the stock bar. Thresholds match the original design.
     */
    public function stockBarLabel(): string
    {
        $percent = $this->stockPercent();

        return match (true) {
            $percent > 50 => 'Good stock',
            $percent > 25 => 'Limited',
            default => 'Low stock',
        };
    }

    public function stockLabel(): string
    {
        return match (true) {
            ! $this->track_inventory => 'Not tracked',
            $this->isOutOfStock() => 'Out of stock',
            $this->isLowStock() => 'Low stock',
            default => 'In stock',
        };
    }

    /* --------------------------------------------------------------- scopes */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Panel search. Wrapped in its own group so it cannot swallow the filters
     * applied around it.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = SqlLike::contains($term);

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('brand', 'like', $like);
        });
    }
}
