<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One selectable size of a product, e.g. "60 count" at its own price and stock.
 */
class ProductPack extends Model
{
    use HasFactory;

    protected $table = 'tbl_product_packs';

    protected $fillable = [
        'label',
        'price',
        'compare_at_price',
        'stock_quantity',
        'is_best_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            // Fixed point, like the parent product: a size chooser that drifts
            // by a cent between the button and the cart is a support ticket.
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_best_value' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Percentage off, for the "Save N%" chip. Null unless the compare-at price
     * is genuinely higher, so a mis-keyed value shows nothing rather than a
     * negative discount.
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
}
