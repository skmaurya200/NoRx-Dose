<?php

namespace App\Http\Resources\APIs;

use App\Models\ProductDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDetail
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ingredients' => $this->ingredients,
            'benefits' => $this->benefits,
            'how_to_use' => $this->how_to_use,
            'storage' => $this->storage,
            'warnings' => $this->warnings,
            'country_of_origin' => $this->country_of_origin,
            'manufacturer' => $this->manufacturer,
            'shelf_life_months' => $this->shelf_life_months,
            'is_vegetarian' => $this->is_vegetarian,
            'is_gluten_free' => $this->is_gluten_free,
            'specifications' => $this->specifications ?? [],
        ];
    }
}
