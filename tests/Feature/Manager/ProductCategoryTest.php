<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/categories';

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

    /* ------------------------------------------------------------- access */

    public function test_a_guest_cannot_touch_the_category_api(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
        $this->postJson(self::ENDPOINT, [])->assertStatus(401);
    }

    public function test_a_storefront_customer_cannot_touch_the_category_api(): void
    {
        // The web guard is listed in sanctum.guard, so a customer does
        // authenticate - EnsureAdmin is what has to stop them.
        $this->actingAs(User::factory()->create())
            ->getJson(self::ENDPOINT)
            ->assertStatus(403);
    }

    /* --------------------------------------------------------- validation */

    public function test_a_category_needs_a_name(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors'])
            ->assertJsonValidationErrors('name');
    }

    public function test_a_parent_that_does_not_exist_is_rejected(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Sleep', 'parent_id' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_a_soft_deleted_parent_is_rejected(): void
    {
        $parent = ProductCategory::factory()->create();
        $parent->delete();

        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Sleep', 'parent_id' => $parent->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    /* -------------------------------------------------------------- create */

    public function test_an_admin_can_create_a_category(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, [
                'name' => 'Sleep & Recovery',
                'description' => 'Wind down and repair.',
                'sort_order' => 3,
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Sleep & Recovery')
            ->assertJsonPath('data.slug', 'sleep-recovery');

        $this->assertDatabaseHas('tbl_product_categories', [
            'name' => 'Sleep & Recovery',
            'slug' => 'sleep-recovery',
            'sort_order' => 3,
        ]);
    }

    public function test_a_duplicate_name_gets_its_own_slug(): void
    {
        ProductCategory::factory()->create(['name' => 'Sleep', 'slug' => 'sleep']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Sleep'])
            ->assertStatus(201)
            // Two categories may share a display name, but the URL cannot
            // collide or one of them becomes unreachable.
            ->assertJsonPath('data.slug', 'sleep-2');
    }

    public function test_a_typed_slug_decides_the_url(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Sleep', 'slug' => 'deep-sleep'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'deep-sleep');
    }

    public function test_a_typed_slug_still_cannot_claim_a_url_already_in_use(): void
    {
        ProductCategory::factory()->create(['slug' => 'deep-sleep']);

        // The override chooses the words, not the uniqueness rules - otherwise
        // one of the two categories becomes unreachable.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Sleep', 'slug' => 'deep-sleep'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'deep-sleep-2');
    }

    public function test_a_slug_with_invalid_characters_is_rejected_with_422(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Sleep', 'slug' => 'Deep Sleep!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_a_blank_slug_is_worked_out_from_the_name(): void
    {
        // What an untouched field posts, which must not read as an empty URL.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Sleep & Recovery', 'slug' => ''])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'sleep-recovery');
    }

    public function test_the_description_keeps_safe_markup_and_drops_a_script(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, [
                'name' => 'Sleep',
                'description' => '<p>Wind <strong>down</strong>.</p><script>alert(1)</script>',
            ])
            ->assertStatus(201);

        $this->assertSame(
            '<p>Wind <strong>down</strong>.</p>',
            ProductCategory::query()->where('name', 'Sleep')->value('description'),
        );
    }

    public function test_a_description_of_empty_markup_is_stored_as_null(): void
    {
        // What the editor leaves behind when its content is deleted.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Sleep', 'description' => '<p><br></p>'])
            ->assertStatus(201);

        $this->assertNull(ProductCategory::query()->where('name', 'Sleep')->value('description'));
    }

    /* -------------------------------------------------------------- update */

    public function test_an_admin_can_update_a_category(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Old name']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$category->id, [
                'name' => 'New name',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New name')
            ->assertJsonPath('data.slug', 'new-name')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_typed_slug_wins_over_a_name_changed_in_the_same_save(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Old name', 'slug' => 'old-name']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$category->id, [
                'name' => 'New name',
                'slug' => 'chosen-url',
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'chosen-url');
    }

    public function test_an_unchanged_name_and_a_blank_slug_leave_the_url_alone(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Sleep', 'slug' => 'deep-sleep']);

        // Renaming is the only thing that may invalidate a URL that is already
        // indexed and linked to; editing anything else must not.
        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$category->id, [
                'name' => 'Sleep',
                'slug' => '',
                'sort_order' => 9,
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'deep-sleep');
    }

    public function test_a_category_cannot_become_its_own_parent(): void
    {
        $category = ProductCategory::factory()->create();

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$category->id, [
                'name' => $category->name,
                'parent_id' => $category->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_a_category_cannot_be_moved_under_its_own_child(): void
    {
        $parent = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->childOf($parent)->create();

        // Would make the tree circular: parent -> child -> parent.
        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$parent->id, [
                'name' => $parent->name,
                'parent_id' => $child->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /* -------------------------------------------------------------- delete */

    public function test_a_category_holding_products_cannot_be_deleted(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->asAdmin()
            ->deleteJson(self::ENDPOINT.'/'.$category->id)
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertNotSoftDeleted('tbl_product_categories', ['id' => $category->id]);
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $parent = ProductCategory::factory()->create();
        ProductCategory::factory()->childOf($parent)->create();

        $this->asAdmin()
            ->deleteJson(self::ENDPOINT.'/'.$parent->id)
            ->assertStatus(409);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $category = ProductCategory::factory()->create();

        $this->asAdmin()
            ->deleteJson(self::ENDPOINT.'/'.$category->id)
            ->assertOk();

        $this->assertSoftDeleted('tbl_product_categories', ['id' => $category->id]);
    }

    /* -------------------------------------------------------------- toggle */

    public function test_an_admin_can_toggle_visibility(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT.'/'.$category->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($category->fresh()->is_active);
    }

    /* --------------------------------------------------------------- lists */

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        ProductCategory::factory()->create(['name' => 'Sleep Support', 'is_active' => true]);
        ProductCategory::factory()->create(['name' => 'Daily Greens', 'is_active' => true]);
        ProductCategory::factory()->inactive()->create(['name' => 'Sleep Archive']);

        $this->asAdmin()
            ->getJson(self::ENDPOINT.'?search=Sleep')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->asAdmin()
            ->getJson(self::ENDPOINT.'?search=Sleep&status=active')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_the_page_size_is_capped(): void
    {
        ProductCategory::factory()->count(3)->create();

        // Without the clamp this would be an invitation to pull the whole table.
        $this->asAdmin()
            ->getJson(self::ENDPOINT.'?per_page=100000')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', (int) config('admin.catalogue.max_per_page'));
    }

    public function test_a_search_wildcard_is_matched_literally(): void
    {
        ProductCategory::factory()->create(['name' => 'Sleep']);
        ProductCategory::factory()->create(['name' => 'Greens']);

        // An unescaped "%" would match every row instead of none.
        $this->asAdmin()
            ->getJson(self::ENDPOINT.'?search=%')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    /* ---------------------------------------------------------- accordions */

    public function test_a_category_can_carry_its_own_sections(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, [
                'name' => 'Sleep',
                'accordions' => [
                    ['label' => 'How to choose', 'body' => '<p>Start with the lowest dose.</p>'],
                    ['label' => 'What the numbers mean', 'body' => '<h3>Strength</h3><p>Per capsule.</p>'],
                ],
            ])
            ->assertStatus(201);

        $sections = ProductCategory::query()->where('name', 'Sleep')->firstOrFail()->accordions;

        $this->assertCount(2, $sections);
        $this->assertSame('How to choose', $sections[0]['label']);
        $this->assertSame('<h3>Strength</h3><p>Per capsule.</p>', $sections[1]['body']);
    }

    public function test_a_section_can_carry_an_icon_and_falls_back_without_one(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, [
                'name' => 'Sleep',
                'accordions' => [
                    ['label' => 'How to choose', 'icon' => '💊', 'body' => '<p>Start low.</p>'],
                    ['label' => 'What it costs', 'body' => '<p>Per pack.</p>'],
                ],
            ])
            ->assertStatus(201);

        $sections = ProductCategory::query()->where('name', 'Sleep')->firstOrFail()->accordions;

        $this->assertSame('💊', $sections[0]['icon']);
        // Blank rather than absent, so the storefront's fallback is the only
        // branch and the shape of a row never varies.
        $this->assertSame('', $sections[1]['icon']);
    }

    public function test_a_section_keeps_safe_markup_and_drops_a_script(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, [
                'name' => 'Sleep',
                'accordions' => [[
                    'label' => 'How to choose',
                    'body' => '<p>Start <strong>low</strong>.</p><script>alert(1)</script>',
                ]],
            ])
            ->assertStatus(201);

        $this->assertSame(
            '<p>Start <strong>low</strong>.</p>',
            ProductCategory::query()->where('name', 'Sleep')->firstOrFail()->accordions[0]['body'],
        );
    }

    public function test_a_half_filled_section_is_dropped_rather_than_stored(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, [
                'name' => 'Sleep',
                'accordions' => [
                    ['label' => 'How to choose', 'body' => '<p>Start low.</p>'],
                    // A heading with an empty editor under it is a blank row,
                    // even though the markup string is not empty.
                    ['label' => 'Nothing under me', 'body' => '<p><br></p>'],
                    ['label' => '', 'body' => '<p>No heading.</p>'],
                ],
            ])
            ->assertStatus(201);

        $this->assertCount(1, ProductCategory::query()->where('name', 'Sleep')->firstOrFail()->accordions);
    }

    public function test_deleting_every_section_really_clears_them(): void
    {
        $category = ProductCategory::factory()->create([
            'accordions' => [['label' => 'How to choose', 'body' => '<p>Start low.</p>']],
        ]);

        // What the browser posts once the last row has been deleted: no
        // accordions keys at all, and the flag saying the form owns them.
        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$category->id, [
                'name' => $category->name,
                'accordions_present' => 1,
            ])
            ->assertOk();

        $this->assertNull($category->refresh()->accordions);
    }

    public function test_editing_something_else_leaves_the_sections_alone(): void
    {
        $category = ProductCategory::factory()->create([
            'accordions' => [['label' => 'How to choose', 'body' => '<p>Start low.</p>']],
        ]);

        // No flag and no rows: a payload that never mentions them must not be
        // read as "clear them".
        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$category->id, ['name' => 'Renamed'])
            ->assertOk();

        $this->assertCount(1, $category->refresh()->accordions);
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_new_category_screen_offers_a_slug_field_that_follows_the_name(): void
    {
        $response = $this->asAdmin()->get('/manager/categories/create');

        // data-slug-from is what catalogue.js binds the auto-fill to, so the
        // attribute is the contract between the screen and the script.
        $response->assertSee('data-slug-from="name"', false);
    }

    public function test_the_new_category_screen_writes_the_description_through_the_editor(): void
    {
        $response = $this->asAdmin()->get('/manager/categories/create');

        // The same editor the journal uses: a hidden input the toolbar keeps
        // in step, rather than a plain textarea.
        $response->assertSee('data-editor-input="description"', false);
        $response->assertSee('js/manager/editor.js', false);
    }

    public function test_the_edit_screen_prefills_the_stored_slug_and_description(): void
    {
        $category = ProductCategory::factory()->create([
            'slug' => 'deep-sleep',
            'description' => '<p>Wind down.</p>',
        ]);

        $response = $this->asAdmin()->get('/manager/categories/'.$category->id.'/edit');

        $response->assertSee('value="deep-sleep"', false);
        $response->assertSee('&lt;p&gt;Wind down.&lt;/p&gt;', false);
    }

    /* -------------------------------------------------------------- upload */

    public function test_an_uploaded_image_lands_in_the_public_folder(): void
    {
        $response = $this->asAdmin()->post(self::ENDPOINT, [
            'name' => 'With image',
            'image' => UploadedFile::fake()->image('photo.jpg', 400, 400),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $path = ProductCategory::where('name', 'With image')->firstOrFail()->image_path;

        $this->assertStringStartsWith('uploads/categories/', $path);
        // The whole point of the convention: it is served from public/, and
        // there is no storage disk involved anywhere.
        $this->assertFileExists(public_path($path));

        @unlink(public_path($path));
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        $this->asAdmin()->post(self::ENDPOINT, [
            'name' => 'Bad upload',
            'image' => UploadedFile::fake()->create('payload.php', 8, 'application/x-php'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->assertDatabaseMissing('tbl_product_categories', ['name' => 'Bad upload']);
    }
}
