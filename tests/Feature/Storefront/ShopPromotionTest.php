<?php

namespace Tests\Feature\Storefront;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_live_public_coupon_is_rendered_as_the_shop_promotion(): void
    {
        Coupon::factory()->percent(20, 200)->create([
            'code' => 'LATER20',
            'description' => '20% off larger orders',
            'sort_order' => 20,
        ]);
        Coupon::factory()->percent(12, 99)->create([
            'code' => 'SAVE12',
            'description' => '12% off orders over $99',
            'ends_at' => '2026-10-01 12:00:00',
            'sort_order' => 10,
        ]);

        $response = $this->get('/shop');

        $response
            ->assertOk()
            ->assertSee('12% off orders over $99')
            ->assertSee('SAVE12')
            ->assertSee('data-promotion-ends-at="2026-10-01T12:00:00+00:00"', false)
            ->assertDontSee('LATER20');
    }

    public function test_a_coupon_without_an_end_time_has_no_fake_countdown(): void
    {
        Coupon::factory()->percent(10, 99)->create([
            'code' => 'SAVE10',
            'description' => null,
        ]);

        $response = $this->get('/shop');

        $response
            ->assertOk()
            ->assertSee('Seasonal offer —')
            ->assertSee('10% off — On orders over $99.00')
            ->assertDontSee('id="clock"', false)
            ->assertDontSee('data-promotion-ends-at=', false);
    }

    public function test_the_shop_hides_the_promotion_when_no_coupon_can_be_advertised(): void
    {
        Coupon::factory()->disabled()->create(['code' => 'DISABLED']);
        Coupon::factory()->private()->create(['code' => 'PRIVATE']);
        Coupon::factory()->expired()->create(['code' => 'EXPIRED']);

        $response = $this->get('/shop');

        $response
            ->assertOk()
            ->assertDontSee('id="promotion"', false)
            ->assertDontSee('DISABLED')
            ->assertDontSee('PRIVATE')
            ->assertDontSee('EXPIRED');
    }

    public function test_the_shop_escapes_a_manager_supplied_promotion_description(): void
    {
        Coupon::factory()->create([
            'code' => 'SAFE10',
            'description' => '<script>alert("promotion")</script>',
        ]);

        $response = $this->get('/shop');

        $response
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("promotion")</script>', false);
    }
}
