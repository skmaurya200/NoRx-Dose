<?php

namespace App\Http\Resources\APIs;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,

            'customer' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
            ],

            'billing' => [
                'street' => $this->street,
                'city' => $this->city,
                'state' => $this->state,
                'state_name' => $this->stateName(),
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],

            'notes' => $this->notes,

            'shipping_method' => $this->shipping_method,
            'shipping_method_label' => $this->shipping_method_label,

            'coupon_code' => $this->coupon_code,
            'coupon_description' => $this->coupon_description,

            'totals' => [
                'subtotal' => $this->subtotal,
                'discount' => $this->discount_total,
                'shipping' => $this->shipping_total,
                'total' => $this->grand_total,
                'currency' => $this->currency,
            ],

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name,
                'pack_label' => $item->pack_label,
                'sku' => $item->sku,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ])),

            // Brand and last four only. The stored number is never resolved
            // into a response, whatever is loaded.
            'payment' => $this->whenLoaded('payment', fn () => [
                'method' => $this->payment->method,
                'card' => $this->payment->maskedNumber(),
                'expiry' => $this->payment->expiryLabel(),
                'status' => $this->payment->status,
            ]),

            'placed_at' => $this->placed_at?->toIso8601String(),
        ];
    }
}
