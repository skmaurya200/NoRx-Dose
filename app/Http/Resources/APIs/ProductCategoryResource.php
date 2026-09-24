<?php

namespace App\Http\Resources\APIs;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductCategory
 */
class ProductCategoryResource extends JsonResource
{
    /**
     * An explicit allow-list: adding a column to tbl_product_categories must
     * never leak it into a response by accident.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->imageUrl(),

            'parent_id' => $this->parent_id,
            // whenLoaded keeps this from firing a query per row in a list.
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ]),

            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,

            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,

            'products_count' => $this->whenCounted('products'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
