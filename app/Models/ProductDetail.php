<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The long-form half of a product: everything a customer reads on the product
 * page but that no listing or cart query ever needs. Split out so the hot
 * tbl_products rows stay narrow.
 */
class ProductDetail extends Model
{
    use HasFactory;

    protected $table = 'tbl_product_details';

    protected $fillable = [
        'ingredients',
        'benefits',
        'how_to_use',
        'storage',
        'warnings',
        'country_of_origin',
        'manufacturer',
        'shelf_life_months',
        'is_vegetarian',
        'is_gluten_free',
        'specifications',
        'extra_sections',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'extra_sections' => 'array',
            'is_vegetarian' => 'boolean',
            'is_gluten_free' => 'boolean',
            'shelf_life_months' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
