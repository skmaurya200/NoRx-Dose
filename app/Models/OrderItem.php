<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order. Every customer-facing field is a snapshot taken when
 * the order was placed, so a later price change or a renamed product does not
 * rewrite history.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'tbl_order_items';

    protected $fillable = [
        'product_id',
        'pack_id',
        'name',
        'pack_label',
        'sku',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
            'product_id' => 'integer',
            'pack_id' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * The product as it is today, or null once it has been removed from the
     * catalogue. Never use this for the name or the price on a receipt.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(ProductPack::class, 'pack_id');
    }

    /**
     * "Calm Magnesium Complex — 60 count"
     */
    public function displayName(): string
    {
        return $this->pack_label ? $this->name.' — '.$this->pack_label : $this->name;
    }
}
