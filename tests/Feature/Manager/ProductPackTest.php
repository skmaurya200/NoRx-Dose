<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pack sizes - the "Select size" chooser on the product page.
 */
class ProductPackTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/products';

    private Admin $admin;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->category = ProductCategory::factory()->create();
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
            'category_id' => $this->category->id,
            'name' => 'Calm Magnesium Complex',
            'price' => 42.00,
            'currency' => 'USD',
            'status' => 'draft',
            'track_inventory' => true,
            'stock_quantity' => 25,
        ], $overrides);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function packs(): array
    {
        return [
            ['label' => '30 count', 'price' => 38.00, 'compare_at_price' => 46.00, 'stock_quantity' => 12],
            ['label' => '60 count', 'price' => 68.00, 'compare_at_price' => 92.00, 'stock_quantity' => 24, 'is_best_value' => true],
            ['label' => '90 count', 'price' => 96.00, 'compare_at_price' => 138.00, 'stock_quantity' => 18],
        ];
    }

    /* -------------------------------------------------------------- create */

    public function test_packs_are_stored_with_the_product(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $this->packs()]))
            ->assertStatus(201)
            ->assertJsonPath('data.packs.0.label', '30 count')
            ->assertJsonPath('data.packs.1.is_best_value', true)
            ->assertJsonCount(3, 'data.packs');

        $this->assertDatabaseCount('tbl_product_packs', 3);
    }

    public function test_the_first_pack_sets_the_products_headline_price(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'price' => 999.00,           // deliberately wrong
                'packs' => $this->packs(),
            ]))
            ->assertStatus(201);

        $product = Product::firstOrFail();

        // Otherwise the listing card and the buy box would quote different
        // figures for the same product.
        $this->assertSame('38.00', (string) $product->price);
        $this->assertSame('46.00', (string) $product->compare_at_price);
    }

    public function test_pack_order_is_preserved(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $this->packs()]))
            ->assertStatus(201);

        $labels = Product::firstOrFail()->packs->pluck('label')->all();

        $this->assertSame(['30 count', '60 count', '90 count'], $labels);
    }

    public function test_blank_pack_rows_are_discarded(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'packs' => [
                    ['label' => '30 count', 'price' => 38.00],
                    ['label' => '', 'price' => ''],
                ],
            ]))
            ->assertStatus(201);

        $this->assertDatabaseCount('tbl_product_packs', 1);
    }

    public function test_a_product_can_be_created_without_packs(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(201);

        $this->assertDatabaseCount('tbl_product_packs', 0);
        // Its own price stands when there are no sizes to override it.
        $this->assertSame('42.00', (string) Product::firstOrFail()->price);
    }

    /* ---------------------------------------------------------- validation */

    public function test_a_pack_price_is_required_once_a_label_is_given(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'packs' => [['label' => '30 count']],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('packs.0.price');
    }

    public function test_a_pack_label_is_required_once_a_price_is_given(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'packs' => [['price' => 38.00]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('packs.0.label');
    }

    public function test_two_packs_cannot_share_a_label(): void
    {
        // (product_id, label) is unique in the database; this turns the clash
        // into a field error instead of a 500.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'packs' => [
                    ['label' => '30 count', 'price' => 38.00],
                    ['label' => '30 Count', 'price' => 40.00],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('packs.1.label');
    }

    public function test_a_pack_compare_at_price_must_beat_its_own_price(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'packs' => [['label' => '30 count', 'price' => 38.00, 'compare_at_price' => 30.00]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('packs.0.compare_at_price');
    }

    public function test_only_one_pack_can_be_the_best_value(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'packs' => [
                    ['label' => '30 count', 'price' => 38.00, 'is_best_value' => true],
                    ['label' => '60 count', 'price' => 68.00, 'is_best_value' => true],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('packs');
    }

    public function test_the_number_of_packs_is_capped(): void
    {
        $packs = [];

        for ($i = 1; $i <= 13; $i++) {
            $packs[] = ['label' => $i.' count', 'price' => $i * 10];
        }

        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $packs]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('packs');
    }

    /* -------------------------------------------------------------- update */

    public function test_editing_keeps_the_id_of_a_pack_whose_label_is_unchanged(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $this->packs()]))
            ->assertStatus(201);

        $product = Product::firstOrFail();
        $originalId = $product->packs()->where('label', '60 count')->value('id');

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->payload([
                'packs' => [
                    ['label' => '30 count', 'price' => 38.00],
                    ['label' => '60 count', 'price' => 72.00],
                ],
            ]))
            ->assertOk();

        // An order line will point at a pack, so re-creating the row on every
        // save would orphan it.
        $this->assertSame(
            $originalId,
            $product->fresh()->packs()->where('label', '60 count')->value('id'),
        );
    }

    public function test_packs_removed_from_the_form_are_deleted(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $this->packs()]))
            ->assertStatus(201);

        $product = Product::firstOrFail();

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->payload([
                'packs' => [['label' => '30 count', 'price' => 38.00]],
            ]))
            ->assertOk();

        $this->assertDatabaseCount('tbl_product_packs', 1);
    }

    public function test_an_empty_packs_array_clears_every_size(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $this->packs()]))
            ->assertStatus(201);

        $product = Product::firstOrFail();

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->payload(['packs' => []]))
            ->assertOk();

        $this->assertDatabaseCount('tbl_product_packs', 0);
    }

    /**
     * The panel form posts no packs[] keys once the operator has deleted every
     * row, so the flag it carries is what tells the update the sizes are gone.
     */
    public function test_the_packs_present_flag_clears_every_size(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $this->packs()]))
            ->assertStatus(201);

        $product = Product::firstOrFail();

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->payload(['packs_present' => '1']))
            ->assertOk();

        $this->assertDatabaseCount('tbl_product_packs', 0);
    }

    public function test_the_form_carries_the_packs_present_flag(): void
    {
        $product = Product::factory()->for($this->category, 'category')->create();

        $this->asAdmin()
            ->get('/manager/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('name="packs_present"', false);
    }

    public function test_an_update_that_never_mentions_packs_leaves_them_alone(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $this->packs()]))
            ->assertStatus(201);

        $product = Product::firstOrFail();

        $payload = $this->payload();
        $this->assertArrayNotHasKey('packs', $payload);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $payload)
            ->assertOk();

        // Absent means "leave it alone", the same rule published_at follows.
        $this->assertDatabaseCount('tbl_product_packs', 3);
    }

    public function test_deleting_a_product_takes_its_packs_with_it(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['packs' => $this->packs()]))
            ->assertStatus(201);

        $product = Product::firstOrFail();
        $product->forceDelete();

        $this->assertDatabaseCount('tbl_product_packs', 0);
    }
}
