<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Discount codes, managed from the panel.
 */
class CouponTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/coupons';

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SAVE10',
            'description' => '10% off orders over $99',
            'type' => 'percent',
            'value' => 10,
            'min_order_amount' => 99,
            'is_active' => true,
            'is_public' => true,
        ], $overrides);
    }

    /* ---------------------------------------------------------------- auth */

    public function test_the_endpoints_are_closed_to_guests(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    /* -------------------------------------------------------------- create */

    public function test_a_coupon_is_created(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.value_label', '10% off');

        $this->assertDatabaseHas('tbl_coupons', [
            'code' => 'SAVE10',
            'type' => 'percent',
            'min_order_amount' => 99,
        ]);
    }

    public function test_a_code_is_stored_upper_cased(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['code' => 'save10']))
            ->assertStatus(201);

        $this->assertDatabaseHas('tbl_coupons', ['code' => 'SAVE10']);
    }

    public function test_two_coupons_cannot_share_a_code(): void
    {
        Coupon::factory()->create(['code' => 'SAVE10']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_a_code_cannot_contain_spaces(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['code' => 'SAVE 10']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    /* ---------------------------------------------------------------- rules */

    public function test_a_percentage_cannot_exceed_one_hundred(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['value' => 120]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    public function test_a_fixed_discount_cannot_carry_a_maximum(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'type' => 'fixed',
                'value' => 15,
                'max_discount_amount' => 20,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('max_discount_amount');
    }

    public function test_the_end_date_must_follow_the_start_date(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'ends_at' => now()->toDateTimeString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_a_minimum_order_amount_is_optional(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['min_order_amount' => null]))
            ->assertStatus(201);

        $this->assertDatabaseHas('tbl_coupons', ['code' => 'SAVE10', 'min_order_amount' => 0]);
    }

    /* -------------------------------------------------------------- update */

    public function test_a_coupon_is_updated(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'value' => 10]);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$coupon->id, $this->payload(['value' => 25]))
            ->assertOk()
            ->assertJsonPath('data.value_label', '25% off');
    }

    public function test_a_coupon_keeps_its_own_code_on_update(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$coupon->id, $this->payload(['description' => 'Reworded']))
            ->assertOk();
    }

    /**
     * used_count belongs to the orders that were placed, not to the form.
     */
    public function test_the_redemption_count_cannot_be_set_from_the_form(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'used_count' => 3]);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$coupon->id, $this->payload(['used_count' => 999]))
            ->assertOk();

        $this->assertSame(3, $coupon->fresh()->used_count);
    }

    /* -------------------------------------------------------- toggle/delete */

    public function test_a_coupon_can_be_disabled_and_re_enabled(): void
    {
        $coupon = Coupon::factory()->create(['is_active' => true]);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT.'/'.$coupon->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT.'/'.$coupon->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_a_coupon_is_soft_deleted(): void
    {
        $coupon = Coupon::factory()->create();

        $this->asAdmin()
            ->deleteJson(self::ENDPOINT.'/'.$coupon->id)
            ->assertOk();

        $this->assertSoftDeleted('tbl_coupons', ['id' => $coupon->id]);
    }

    /* --------------------------------------------------------------- screens */

    public function test_the_panel_screens_render(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);

        $this->asAdmin()->get('/manager/coupons')->assertOk()->assertSee('SAVE10');
        $this->asAdmin()->get('/manager/coupons/create')->assertOk();
        $this->asAdmin()->get('/manager/coupons/'.$coupon->id.'/edit')->assertOk()->assertSee('SAVE10');
    }

    /* ----------------------------------------------------------- arithmetic */

    public function test_a_percentage_discount_is_calculated_off_the_subtotal(): void
    {
        $coupon = Coupon::factory()->percent(10, 99)->create();

        $this->assertSame(0.0, $coupon->discountFor(50));   // under the minimum
        $this->assertSame(9.9, $coupon->discountFor(99));   // exactly on it
        $this->assertSame(20.0, $coupon->discountFor(200));
    }

    public function test_a_percentage_discount_respects_its_cap(): void
    {
        $coupon = Coupon::factory()->percent(20, 0)->create(['max_discount_amount' => 60]);

        $this->assertSame(20.0, $coupon->discountFor(100));
        $this->assertSame(60.0, $coupon->discountFor(900));
    }

    public function test_a_fixed_discount_never_exceeds_the_subtotal(): void
    {
        $coupon = Coupon::factory()->fixed(15, 0)->create();

        $this->assertSame(15.0, $coupon->discountFor(120));
        $this->assertSame(10.0, $coupon->discountFor(10));
    }

    public function test_an_unusable_coupon_discounts_nothing(): void
    {
        $this->assertSame(0.0, Coupon::factory()->percent(10)->expired()->create()->discountFor(500));
        $this->assertSame(0.0, Coupon::factory()->percent(10)->disabled()->create()->discountFor(500));
        $this->assertSame(0.0, Coupon::factory()->percent(10)->scheduled()->create()->discountFor(500));
        $this->assertSame(0.0, Coupon::factory()->percent(10)->exhausted()->create()->discountFor(500));
    }
}
