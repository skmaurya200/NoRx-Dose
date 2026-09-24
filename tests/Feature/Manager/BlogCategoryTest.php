<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Journal categories - the chips above the public post list.
 */
class BlogCategoryTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/blog/categories';

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

    public function test_the_endpoints_are_closed_to_guests(): void
    {
        $this->postJson(self::ENDPOINT, ['name' => 'Ingredients'])->assertUnauthorized();
    }

    public function test_a_category_is_created_with_a_derived_slug(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Behind the brand', 'is_active' => true])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'behind-the-brand');
    }

    public function test_two_categories_cannot_share_a_name(): void
    {
        BlogCategory::factory()->create(['name' => 'Routines']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['name' => 'Routines'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'A category with that name already exists.');
    }

    public function test_renaming_a_category_updates_its_slug(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Old name']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$category->id, ['name' => 'New name'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-name');
    }

    public function test_a_category_can_be_hidden_and_shown(): void
    {
        $category = BlogCategory::factory()->create(['is_active' => true]);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT.'/'.$category->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    /**
     * The foreign key would quietly null the posts' category, so the operator
     * is told what they are about to detach instead.
     */
    public function test_a_category_with_posts_cannot_be_deleted(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPost::factory()->for($category, 'category')->create();

        $this->asAdmin()
            ->deleteJson(self::ENDPOINT.'/'.$category->id)
            ->assertStatus(409);

        $this->assertDatabaseHas('tbl_blog_categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_an_empty_category_is_deleted(): void
    {
        $category = BlogCategory::factory()->create();

        $this->asAdmin()->deleteJson(self::ENDPOINT.'/'.$category->id)->assertOk();

        $this->assertSoftDeleted('tbl_blog_categories', ['id' => $category->id]);
    }

    public function test_the_panel_screens_render(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Ingredients']);

        $this->asAdmin()->get('/manager/blog/categories')->assertOk()->assertSee('Ingredients');
        $this->asAdmin()->get('/manager/blog/categories/create')->assertOk();
        $this->asAdmin()->get('/manager/blog/categories/'.$category->id.'/edit')
            ->assertOk()->assertSee('Ingredients');
    }
}
