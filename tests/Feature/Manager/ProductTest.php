<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductTest extends TestCase
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
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name' => 'Calm Magnesium Complex',
            'price' => 42.00,
            'currency' => 'USD',
            'status' => 'draft',
            'track_inventory' => true,
            'stock_quantity' => 25,
            'low_stock_threshold' => 5,
        ], $overrides);
    }

    /* ------------------------------------------------------------- access */

    public function test_a_guest_cannot_touch_the_product_api(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
        $this->postJson(self::ENDPOINT, [])->assertStatus(401);
    }

    public function test_a_storefront_customer_cannot_touch_the_product_api(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(self::ENDPOINT)
            ->assertStatus(403);
    }

    /* --------------------------------------------------------- validation */

    public function test_the_required_fields_are_enforced(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'data', 'errors'])
            // "packs", not "price": the panel stopped asking for a price of
            // its own, so an empty payload is missing the sizes that would have
            // set one.
            ->assertJsonValidationErrors(['category_id', 'name', 'packs', 'status']);
    }

    public function test_the_first_pack_size_sets_what_the_product_costs(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'price' => null,
                'packs' => [
                    ['label' => '30', 'price' => 19.50, 'compare_at_price' => 25.00, 'stock_quantity' => 8],
                    ['label' => '60', 'price' => 34.00, 'stock_quantity' => 4],
                ],
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.price', '19.50')
            ->assertJsonPath('data.compare_at_price', '25.00');
    }

    public function test_a_product_with_no_price_and_no_sizes_is_rejected(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload(['price' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('packs');

        $this->assertDatabaseCount('tbl_products', 0);
    }

    public function test_the_sizes_win_over_a_price_sent_beside_them(): void
    {
        // Deliberate, and the reason the panel stopped asking for a price at
        // all: a listing card and a buy box quoting different figures for the
        // same product is a mismatch the customer finds at checkout.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'price' => 99.00,
                'packs' => [['label' => '30', 'price' => 19.50, 'stock_quantity' => 8]],
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.price', '19.50');
    }

    public function test_changing_the_first_size_reprices_the_product(): void
    {
        $product = Product::factory()->for($this->category, 'category')->create(['price' => 19.50]);
        $product->packs()->create(['label' => '30', 'price' => 19.50, 'stock_quantity' => 8, 'sort_order' => 1]);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->validPayload([
                'name' => $product->name,
                'price' => null,
                'packs_present' => 1,
                'packs' => [['label' => '30', 'price' => 22.00, 'stock_quantity' => 8]],
            ]))
            ->assertOk()
            ->assertJsonPath('data.price', '22.00');
    }

    public function test_a_negative_price_is_rejected(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload(['price' => -5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_a_price_beyond_the_column_precision_is_rejected(): void
    {
        // decimal(10,2) tops out below this; without the max rule the database
        // would silently truncate rather than refuse.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload(['price' => 100000000]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_a_compare_at_price_below_the_selling_price_is_rejected(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'price' => 50,
                'compare_at_price' => 40,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('compare_at_price');
    }

    public function test_a_cost_price_above_the_selling_price_is_rejected(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'price' => 20,
                'cost_price' => 30,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cost_price');
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload(['status' => 'published']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_stock_is_required_when_inventory_is_tracked(): void
    {
        $payload = $this->validPayload();
        unset($payload['stock_quantity']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('stock_quantity');
    }

    public function test_stock_is_optional_when_inventory_is_not_tracked(): void
    {
        $payload = $this->validPayload(['track_inventory' => false]);
        unset($payload['stock_quantity']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT, $payload)
            ->assertStatus(201);
    }

    public function test_a_duplicate_sku_is_rejected(): void
    {
        Product::factory()->create(['sku' => 'AW-TAKEN']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload(['sku' => 'AW-TAKEN']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    /* -------------------------------------------------------------- create */

    public function test_an_admin_can_create_a_product(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'short_description' => 'Three forms of magnesium.',
                'ingredients' => 'Magnesium glycinate, citrate, malate',
                'how_to_use' => 'Two capsules before bed.',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Calm Magnesium Complex')
            ->assertJsonPath('data.slug', 'calm-magnesium-complex');

        $product = Product::firstOrFail();

        $this->assertDatabaseHas('tbl_products', ['id' => $product->id, 'status' => 'draft']);

        // The detail row must be created alongside the product, or the product
        // page has nothing to render its tabs from.
        $this->assertDatabaseHas('tbl_product_details', [
            'product_id' => $product->id,
            'ingredients' => 'Magnesium glycinate, citrate, malate',
        ]);
    }

    public function test_a_sku_is_generated_when_none_is_given(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload())
            ->assertStatus(201);

        $this->assertNotEmpty(Product::firstOrFail()->sku);
    }

    public function test_publishing_stamps_the_publish_date(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload(['status' => 'active']))
            ->assertStatus(201);

        $this->assertNotNull(Product::firstOrFail()->published_at);
    }

    public function test_a_draft_is_not_stamped_as_published(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload(['status' => 'draft']))
            ->assertStatus(201);

        $this->assertNull(Product::firstOrFail()->published_at);
    }

    public function test_blank_specification_rows_are_discarded(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'specifications' => [
                    ['label' => 'Serving size', 'value' => '2 capsules'],
                    ['label' => '', 'value' => ''],
                    ['label' => 'Servings', 'value' => ''],
                ],
            ]))
            ->assertStatus(201);

        $this->assertCount(1, Product::firstOrFail()->detail->specifications);
    }

    public function test_rating_columns_cannot_be_set_from_the_request(): void
    {
        // They belong to the reviews module; accepting them here would let
        // anyone with panel access fake a five-star average.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'rating_avg' => 5,
                'rating_count' => 999,
            ]))
            ->assertStatus(201);

        $product = Product::firstOrFail();

        $this->assertSame('0.00', (string) $product->rating_avg);
        $this->assertSame(0, $product->rating_count);
    }

    /* -------------------------------------------------------------- update */

    public function test_an_admin_can_update_a_product(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->validPayload([
                'name' => 'Renamed Product',
                'price' => 55.25,
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Product')
            ->assertJsonPath('data.slug', 'renamed-product')
            ->assertJsonPath('data.price', '55.25');
    }

    public function test_a_product_keeps_its_own_sku_on_update(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'sku' => 'AW-KEEPME',
        ]);

        // Would fail if the unique rule did not ignore the record being edited.
        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->validPayload(['sku' => 'AW-KEEPME']))
            ->assertOk();
    }

    public function test_updating_creates_the_detail_row_when_it_is_missing(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);
        $this->assertNull($product->detail);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->validPayload([
                'benefits' => 'Supports restful sleep',
            ]))
            ->assertOk();

        $this->assertDatabaseHas('tbl_product_details', [
            'product_id' => $product->id,
            'benefits' => 'Supports restful sleep',
        ]);
    }

    public function test_an_update_that_omits_published_at_does_not_unpublish_the_product(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $payload = $this->validPayload(['status' => 'active']);
        $this->assertArrayNotHasKey('published_at', $payload);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $payload)
            ->assertOk();

        // An absent field means "leave it alone". Treating it as null here
        // would quietly pull a live product off the storefront.
        $this->assertNotNull($product->fresh()->published_at);
    }

    public function test_an_update_that_omits_optional_fields_leaves_them_intact(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand' => 'NoRx Dose',
            'cost_price' => 11.11,
        ]);

        $payload = $this->validPayload();
        unset($payload['brand'], $payload['cost_price']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $payload)
            ->assertOk();

        $fresh = $product->fresh();
        $this->assertSame('NoRx Dose', $fresh->brand);
        $this->assertSame('11.11', (string) $fresh->cost_price);
    }

    public function test_an_empty_value_does_clear_the_field(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
        ]);

        // Explicitly sent and blank is a deliberate "clear it", unlike absent.
        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->validPayload([
                'status' => 'draft',
                'published_at' => '',
            ]))
            ->assertOk();

        $this->assertNull($product->fresh()->published_at);
    }

    public function test_the_category_id_is_returned_as_an_integer(): void
    {
        $this->asAdmin()
            ->post(self::ENDPOINT, $this->validPayload(), ['Accept' => 'application/json'])
            ->assertStatus(201)
            // Multipart sends everything as a string; the cast is what keeps
            // the API contract honest.
            ->assertJsonPath('data.category_id', $this->category->id);
    }

    /* -------------------------------------------------------------- status */

    public function test_the_status_can_be_changed_on_its_own(): void
    {
        $product = Product::factory()->draft()->create(['category_id' => $this->category->id]);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT.'/'.$product->id.'/status', ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertNotNull($product->fresh()->published_at);
    }

    public function test_an_invalid_status_change_is_rejected(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT.'/'.$product->id.'/status', ['status' => 'nonsense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /* -------------------------------------------------------------- delete */

    public function test_a_product_is_soft_deleted(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        $this->asAdmin()
            ->deleteJson(self::ENDPOINT.'/'.$product->id)
            ->assertOk();

        // Soft, so a past order can still resolve the name and price it sold at.
        $this->assertSoftDeleted('tbl_products', ['id' => $product->id]);

        $this->asAdmin()
            ->getJson(self::ENDPOINT.'/'.$product->id)
            ->assertStatus(404);
    }

    /* --------------------------------------------------------------- lists */

    public function test_the_list_filters_by_category_status_and_stock(): void
    {
        $other = ProductCategory::factory()->create();

        Product::factory()->create(['category_id' => $this->category->id, 'status' => 'active']);
        Product::factory()->draft()->create(['category_id' => $this->category->id]);
        Product::factory()->lowStock()->create(['category_id' => $other->id]);

        $this->asAdmin()->getJson(self::ENDPOINT.'?category_id='.$this->category->id)
            ->assertOk()->assertJsonPath('data.meta.total', 2);

        $this->asAdmin()->getJson(self::ENDPOINT.'?status=draft')
            ->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->asAdmin()->getJson(self::ENDPOINT.'?stock=low')
            ->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_an_unknown_sort_column_falls_back_to_a_safe_default(): void
    {
        Product::factory()->count(2)->create(['category_id' => $this->category->id]);

        // Feeding this straight into orderBy() would be SQL injection; the
        // allow-list means it is simply ignored.
        $this->asAdmin()
            ->getJson(self::ENDPOINT.'?sort=(select+1)&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_products_can_be_searched_by_sku(): void
    {
        Product::factory()->create(['category_id' => $this->category->id, 'sku' => 'AW-FINDME']);
        Product::factory()->create(['category_id' => $this->category->id]);

        $this->asAdmin()
            ->getJson(self::ENDPOINT.'?search=FINDME')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    /* ------------------------------------------------------------ payloads */

    public function test_the_cost_price_is_only_exposed_to_an_admin(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'cost_price' => 12.34,
        ]);

        $this->asAdmin()
            ->getJson(self::ENDPOINT.'/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.cost_price', '12.34');
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_detail_tab_marks_ingredients_and_storage_optional(): void
    {
        $response = $this->asAdmin()->get('/manager/products/create')->assertOk();

        foreach (['ingredients', 'storage'] as $field) {
            // The label has to say what the request already allows, or an
            // operator finds out by saving and hoping.
            $response->assertSee('for="'.$field.'"', false);
        }

        $response->assertSee('All optional.');
    }

    public function test_ingredients_and_storage_sit_below_the_other_detail_fields(): void
    {
        $this->asAdmin()
            ->get('/manager/products/create')
            ->assertOk()
            ->assertSeeInOrder([
                'for="benefits"',
                'for="how_to_use"',
                'for="warnings"',
                'More detail',
                'for="ingredients"',
                'for="storage"',
            ], false);
    }

    public function test_the_edit_screen_prefills_both_optional_fields(): void
    {
        $product = Product::factory()->for($this->category, 'category')->create();

        $product->detail()->create([
            'ingredients' => 'Magnesium glycinate, rice flour',
            'storage' => 'Keep below 25C',
        ]);

        $this->asAdmin()
            ->get('/manager/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('Magnesium glycinate, rice flour')
            ->assertSee('Keep below 25C');
    }

    public function test_a_product_saves_with_both_left_blank(): void
    {
        // What "optional" claims, proven against the endpoint the form posts to.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'ingredients' => '',
                'storage' => '',
            ]))
            ->assertStatus(201);

        $this->assertDatabaseHas('tbl_products', ['name' => 'Calm Magnesium Complex']);
    }

    /**
     * Whether a checkbox on a rendered screen came back ticked.
     *
     * Matched on the tag rather than on a literal string, because Blade's
     *
     * @checked directive decides its own whitespace and a test that pins that
     * breaks on a reformat rather than on a real change.
     */
    private function isChecked(string $html, string $name): bool
    {
        preg_match('/<input[^>]*name="'.preg_quote($name, '/').'"[^>]*>/', $html, $tag);

        return isset($tag[0]) && str_contains($tag[0], 'checked');
    }

    public function test_a_new_product_starts_active_and_orderable(): void
    {
        $response = $this->asAdmin()->get('/manager/products/create')->assertOk();
        $html = $response->getContent();

        // Live on the storefront from the moment it is saved.
        $response->assertSee('<option value="active" selected>', false);

        // Stock is counted per pack size here, so the product-level counter is
        // off and backorders are on - otherwise a new product is unbuyable
        // until somebody finds two switches on another tab.
        $this->assertFalse($this->isChecked($html, 'track_inventory'));
        $this->assertTrue($this->isChecked($html, 'allow_backorder'));
    }

    public function test_the_edit_screen_shows_the_products_own_settings_not_the_defaults(): void
    {
        $product = Product::factory()->for($this->category, 'category')->create([
            'status' => 'draft',
            'track_inventory' => true,
            'allow_backorder' => false,
        ]);

        $response = $this->asAdmin()->get('/manager/products/'.$product->id.'/edit')->assertOk();
        $html = $response->getContent();

        $response->assertSee('<option value="draft" selected>', false);
        $this->assertTrue($this->isChecked($html, 'track_inventory'));
        $this->assertFalse($this->isChecked($html, 'allow_backorder'));
    }

    public function test_the_detail_tab_leads_with_the_three_every_product_needs(): void
    {
        $this->asAdmin()
            ->get('/manager/products/create')
            ->assertOk()
            ->assertSeeInOrder([
                'for="benefits"',
                'for="how_to_use"',
                'for="warnings"',
                'More detail',
            ], false);
    }

    public function test_an_operator_can_add_sections_of_their_own(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'extra_sections' => [
                    ['label' => 'What is in the box', 'body' => 'One bottle and a scoop.'],
                    ['label' => 'Dilution guide', 'body' => 'Two drops in 200ml of water.'],
                ],
            ]))
            ->assertStatus(201);

        $sections = Product::firstOrFail()->detail->extra_sections;

        $this->assertCount(2, $sections);
        $this->assertSame('What is in the box', $sections[0]['label']);
        $this->assertSame('Two drops in 200ml of water.', $sections[1]['body']);
    }

    public function test_a_section_keeps_safe_markup_and_drops_a_script(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'extra_sections' => [[
                    'label' => 'What is in the box',
                    'body' => '<h3>Contents</h3><p>One bottle.</p><script>alert(1)</script>',
                ]],
            ]))
            ->assertStatus(201);

        $this->assertSame(
            '<h3>Contents</h3><p>One bottle.</p>',
            Product::firstOrFail()->detail->extra_sections[0]['body'],
        );
    }

    public function test_deleting_every_section_really_clears_them(): void
    {
        $product = Product::factory()->for($this->category, 'category')->create();

        $product->detail()->create([
            'extra_sections' => [['label' => 'Dilution guide', 'body' => '<p>Two drops.</p>']],
        ]);

        // What the browser posts once the last row has been deleted: no
        // extra_sections keys at all, and the flag saying the form owns them.
        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->validPayload([
                'name' => $product->name,
                'extra_sections_present' => 1,
            ]))
            ->assertOk();

        $this->assertNull($product->refresh()->detail->extra_sections);
    }

    public function test_a_half_filled_section_is_dropped_rather_than_stored(): void
    {
        // The repeater adds an empty row whenever somebody clicks Add and then
        // thinks better of it; an empty heading on the product page is worse
        // than no section at all.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'extra_sections' => [
                    ['label' => 'Dilution guide', 'body' => 'Two drops in water.'],
                    ['label' => 'Heading with nothing under it', 'body' => ''],
                    ['label' => '', 'body' => 'Text with no heading.'],
                ],
            ]))
            ->assertStatus(201);

        $this->assertCount(1, Product::firstOrFail()->detail->extra_sections);
    }

    public function test_editing_something_else_leaves_the_sections_alone(): void
    {
        $product = Product::factory()->for($this->category, 'category')->create();

        $product->detail()->create([
            'extra_sections' => [['label' => 'Dilution guide', 'body' => 'Two drops in water.']],
        ]);

        // A payload that never mentions the repeater must not be read as "clear
        // it" - that is how an edit to the price wipes a page of copy.
        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$product->id, $this->validPayload(['name' => $product->name]))
            ->assertOk();

        $this->assertCount(1, $product->refresh()->detail->extra_sections);
    }

    public function test_too_many_sections_are_rejected_with_422(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'extra_sections' => array_fill(0, 13, ['label' => 'A', 'body' => 'B']),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('extra_sections');
    }

    /* --------------------------------------------------------- description */

    public function test_the_full_description_is_written_through_the_editor(): void
    {
        $this->asAdmin()
            ->get('/manager/products/create')
            ->assertOk()
            ->assertSee('data-editor-input="description"', false)
            ->assertSee('js/manager/editor.js', false);
    }

    public function test_the_full_description_keeps_safe_markup_and_drops_a_script(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload([
                'description' => '<h2>About</h2><p>Made <strong>properly</strong>.</p><script>alert(1)</script>',
            ]))
            ->assertStatus(201);

        $this->assertSame(
            '<h2>About</h2><p>Made <strong>properly</strong>.</p>',
            Product::firstOrFail()->description,
        );
    }

    public function test_a_description_of_empty_markup_is_stored_as_null(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->validPayload(['description' => '<p><br></p>']))
            ->assertStatus(201);

        $this->assertNull(Product::firstOrFail()->description);
    }

    /* -------------------------------------------------------------- upload */

    public function test_an_uploaded_thumbnail_lands_in_the_public_folder(): void
    {
        $response = $this->asAdmin()->post(self::ENDPOINT, $this->validPayload([
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 600, 600),
        ]), ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $path = Product::firstOrFail()->thumbnail_path;

        $this->assertStringStartsWith('uploads/products/', $path);
        $this->assertFileExists(public_path($path));

        @unlink(public_path($path));
    }
}
