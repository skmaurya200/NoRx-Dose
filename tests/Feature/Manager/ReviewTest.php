<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reviews, managed from the panel.
 *
 * The two rules worth protecting: a review is invisible until it is approved,
 * and the product's denormalised rating follows the approved reviews exactly.
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/reviews';

    private Admin $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'name' => 'Calm Magnesium Complex',
        ]);
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
            'author_name' => 'Ada Lovelace',
            'location' => 'Denver',
            'category' => 'quality',
            'rating' => 5,
            'title' => 'Exactly as described',
            'body' => 'Arrived the next morning and the seal was intact. Third order now.',
        ], $overrides);
    }

    /* ---------------------------------------------------------------- auth */

    public function test_the_endpoints_are_closed_to_guests(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();
    }

    /* -------------------------------------------------------------- create */

    public function test_a_review_written_in_the_panel_is_published_immediately(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['product_id' => $this->product->id]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.source', 'manager');

        $review = Review::query()->firstOrFail();

        $this->assertNotNull($review->approved_at);
        $this->assertSame($this->admin->id, $review->approved_by);
    }

    public function test_the_reviewer_email_never_appears_in_a_response(): void
    {
        $response = $this->asAdmin()->postJson(self::ENDPOINT, $this->payload([
            'author_email' => 'ada@example.com',
        ]));

        $response->assertCreated();
        $response->assertJsonMissing(['author_email' => 'ada@example.com']);
    }

    public function test_a_review_needs_a_rating_a_category_and_a_body(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['author_name' => 'Ada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'rating', 'body']);
    }

    /* ---------------------------------------------------------- moderation */

    public function test_approving_and_rejecting_moves_the_product_rating(): void
    {
        $review = Review::factory()->create([
            'product_id' => $this->product->id,
            'rating' => 4,
        ]);

        // Pending: the product has heard nothing about it.
        $this->assertSame(0, (int) $this->product->fresh()->rating_count);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT."/{$review->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame(1, (int) $this->product->fresh()->rating_count);
        $this->assertSame('4.00', (string) $this->product->fresh()->rating_avg);

        $this->asAdmin()
            ->patchJson(self::ENDPOINT."/{$review->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame(0, (int) $this->product->fresh()->rating_count);
    }

    public function test_only_one_review_is_featured_at_a_time(): void
    {
        $first = Review::factory()->featured()->create();

        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['is_featured' => true]))
            ->assertCreated();

        $this->assertFalse((bool) $first->fresh()->is_featured);
    }

    /* ------------------------------------------------------ delete/restore */

    public function test_deleting_a_review_takes_it_out_of_the_product_rating(): void
    {
        $review = Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'rating' => 5,
        ]);

        // The factory writes the row directly, so nudge the product into step.
        $this->asAdmin()->postJson(self::ENDPOINT."/{$review->id}", $this->payload([
            'product_id' => $this->product->id,
            'rating' => 5,
        ]))->assertOk();

        $this->assertSame(1, (int) $this->product->fresh()->rating_count);

        $this->asAdmin()->deleteJson(self::ENDPOINT."/{$review->id}")->assertOk();

        $this->assertSoftDeleted($review);
        $this->assertSame(0, (int) $this->product->fresh()->rating_count);

        $this->asAdmin()->patchJson(self::ENDPOINT."/{$review->id}/restore")->assertOk();

        $this->assertNull($review->fresh()->deleted_at);
        $this->assertSame(1, (int) $this->product->fresh()->rating_count);
    }

    /* -------------------------------------------------------------- screen */

    public function test_the_queue_screen_lists_reviews(): void
    {
        Review::factory()->create(['author_name' => 'Grace Hopper']);

        $this->asAdmin()
            ->get('/manager/reviews')
            ->assertOk()
            ->assertSee('Grace Hopper');
    }

    public function test_the_deleted_view_lists_only_deleted_reviews(): void
    {
        Review::factory()->create(['author_name' => 'Still here']);
        Review::factory()->create(['author_name' => 'Filed away'])->delete();

        $this->asAdmin()
            ->get($this->managerUrl('manager.reviews.index', ['trashed' => 'only']))
            ->assertOk()
            ->assertSee('Filed away')
            ->assertDontSee('Still here');
    }

    public function test_the_form_screens_open(): void
    {
        $review = Review::factory()->create();

        $this->asAdmin()->get('/manager/reviews/create')->assertOk();
        $this->asAdmin()->get("/manager/reviews/{$review->id}/edit")->assertOk();
    }
}
