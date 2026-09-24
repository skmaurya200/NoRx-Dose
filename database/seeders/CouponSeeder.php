<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * A few working discount codes so the cart has something to show before the
 * first one is created in the panel.
 *
 * Keyed by code through updateOrCreate, so running the seeder twice updates
 * the same rows rather than failing on the unique index. used_count is left
 * alone deliberately - it belongs to the orders that were placed.
 */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->coupons() as $coupon) {
            Coupon::updateOrCreate(['code' => $coupon['code']], $coupon);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function coupons(): array
    {
        return [
            [
                'code' => 'SAVE10',
                'description' => '10% off when you spend $99 or more',
                'type' => 'percent',
                'value' => 10,
                'min_order_amount' => 99,
                'max_discount_amount' => null,
                'usage_limit' => null,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'SAVE20',
                'description' => '20% off larger orders, capped at $60',
                'type' => 'percent',
                'value' => 20,
                'min_order_amount' => 200,
                // Without a cap, a $900 order would take $180 off.
                'max_discount_amount' => 60,
                'usage_limit' => null,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'FLAT15',
                'description' => '$15 off any order over $120',
                'type' => 'fixed',
                'value' => 15,
                'min_order_amount' => 120,
                'max_discount_amount' => null,
                'usage_limit' => null,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'WELCOME5',
                'description' => '$5 off your first order — no minimum',
                'type' => 'fixed',
                'value' => 5,
                'min_order_amount' => 0,
                'max_discount_amount' => null,
                'usage_limit' => 500,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 4,
            ],
        ];
    }
}
