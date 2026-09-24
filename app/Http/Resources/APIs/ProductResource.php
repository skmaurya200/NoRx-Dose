<?php

namespace App\Http\Resources\APIs;

use App\Models\Admin;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * An explicit allow-list. Note what is missing on purpose: cost_price is a
     * margin figure and has no business leaving the back office, so it is only
     * included for an authenticated admin.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'brand' => $this->brand,

            'short_description' => $this->short_description,
            'description' => $this->description,

            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),

            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'cost_price' => $this->when($this->isAdmin($request), $this->cost_price),
            'currency' => $this->currency,
            'discount_percent' => $this->discountPercent(),

            'unit' => $this->unit,
            'weight_grams' => $this->weight_grams,

            'track_inventory' => $this->track_inventory,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'allow_backorder' => $this->allow_backorder,
            'stock_label' => $this->stockLabel(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'is_low_stock' => $this->isLowStock(),

            'thumbnail_url' => $this->thumbnailUrl(),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'packs' => ProductPackResource::collection($this->whenLoaded('packs')),

            'status' => $this->status,
            'is_featured' => $this->is_featured,

            'rating_avg' => $this->rating_avg,
            'rating_count' => $this->rating_count,

            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,

            'detail' => new ProductDetailResource($this->whenLoaded('detail')),

            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The same resource will be reused by the storefront API later, where the
     * caller is a customer or nobody at all.
     */
    private function isAdmin(Request $request): bool
    {
        return $request->user() instanceof Admin;
    }
}
