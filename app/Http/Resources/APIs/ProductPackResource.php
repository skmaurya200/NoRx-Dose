<?php

namespace App\Http\Resources\APIs;

use App\Models\ProductPack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductPack
 */
class ProductPackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'discount_percent' => $this->discountPercent(),
            'stock_quantity' => $this->stock_quantity,
            'is_best_value' => $this->is_best_value,
            'sort_order' => $this->sort_order,
        ];
    }
}
