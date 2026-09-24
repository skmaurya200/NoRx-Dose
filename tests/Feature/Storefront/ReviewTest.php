<?php

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reviews as a shopper meets them: the reviews page, the product page, and the
 * form on both.
 *
 * The rule these exist to hold: nothing a visitor writes is visible to anyone
 * until an operator has approved it - including to the visitor who wrote it.
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/storefront/reviews';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'name' => 'Calm Magnesium Complex',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'author_name' => 'Ada Lovelace',
            'author_email' => 'ada@example.com',
            'category' => 'delivery',
            'rating' => 5,
            'body' => 'Arrived the next morning and the seal was intact.',
        ], $overrides);
    }

    /* -------------------------------------------------------------- submit */

    public function test_a_submitted_review_is_pending_and_not_published(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['product_id' => $this->product->id]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $review = Review::query()->firstOrFail();

        $this->assertSame('customer', $review->source);
        $this->assertNull($review->approved_at);

        // Nothing about it reaches the storefront, and the product's rating
        // has not moved.
        $this->get('/reviews')->assertOk()->assertDontSee('seal was intact');
        $this->assertSame(0, (int) $this->product->fresh()->rating_count);
    }

    public function test_a_visitor_cannot_set_their_own_moderation_state(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload([
            'status' => 'approved',
            'is_featured' => true,
            'is_verified' => true,
        ]))->assertCreated();

        $review = Review::query()->firstOrFail();

        $this->assertSame('pending', $review->status);
        $this->assertFalse((bool) $review->is_featured);
        $this->assertFalse((bool) $review->is_verified);
    }

    public function test_a_review_is_marked_verified_when_an_order_matches_the_address(): void
    {
        $this->postJson('/api/storefront/checkout', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'shipping_method' => 'usps',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '4155550132',
            'street' => '2500 Mission Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'postal_code' => '94110',
            'country' => 'US',
            'card_holder' => 'Ada Lovelace',
            'card_number' => '4242424242424242',
            'card_expiry' => '12 / '.str_pad((string) ((now()->year + 2) % 100), 2, '0', STR_PAD_LEFT),
            'card_cvc' => '123',
        ])->assertCreated();

        $this->postJson(self::ENDPOINT, $this->payload(['product_id' => $this->product->id]))
            ->assertCreated();

        $review = Review::query()->firstOrFail();

        $this->assertTrue((bool) $review->is_verified);
        $this->assertSame(Order::query()->value('id'), $review->order_id);
    }

    public function test_the_rating_is_chosen_with_stars_not_a_dropdown(): void
    {
        $product = Product::factory()->create();

        $response = $this->get('/product/'.$product->slug)->assertOk();

        // One radio per star, five down to one, with five pre-selected.
        foreach ([5, 4, 3, 2, 1] as $star) {
            $response->assertSee('id="rstar'.$star.'" name="rrating" value="'.$star.'"', false);
        }

        $response->assertSee('role="radiogroup"', false);
        $response->assertDontSee('<select id="rrating">', false);
    }

    public function test_the_form_asks_for_four_things_and_no_more(): void
    {
        $product = Product::factory()->create();

        $response = $this->get('/product/'.$product->slug)->assertOk();

        // Name and email share a row; the review body has its own.
        $response->assertSee('prForm__pair', false);
        $response->assertSee('id="rname"', false);
        $response->assertSee('id="remail"', false);
        $response->assertSee('id="rtext"', false);

        // The "What is this about?" picker is gone - a customer writing about a
        // product is writing about the product.
        $response->assertDontSee('id="rcat"', false);
    }

    public function test_the_breakdown_shows_how_many_gave_each_rating(): void
    {
        $product = Product::factory()->create();

        foreach ([5, 5, 5, 4, 2] as $rating) {
            Review::factory()->approved()->create([
                'product_id' => $product->id,
                'rating' => $rating,
            ]);
        }

        $response = $this->get('/product/'.$product->slug)->assertOk();

        // Three of five, one of four, none of three, one of two, none of one -
        // and the bar widths are the same figures as percentages.
        $response->assertSee('style="width:60%"', false);
        $response->assertSee('style="width:20%"', false);
        $response->assertSee('style="width:0%"', false);

        $response->assertSee('5 stars: 3 reviews (60%)', false);
        $response->assertSee('3 stars: 0 reviews (0%)', false);
    }

    public function test_the_breakdown_counts_only_approved_reviews(): void
    {
        $product = Product::factory()->create();

        Review::factory()->approved()->create(['product_id' => $product->id, 'rating' => 5]);
        Review::factory()->create(['product_id' => $product->id, 'rating' => 1]);

        // The pending one-star must not drag the published breakdown down.
        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('5 stars: 1 review (100%)', false)
            ->assertSee('1 stars: 0 reviews (0%)', false);
    }

    public function test_the_form_is_validated(): void
    {
        // No 'category': the storefront form stopped asking, so an absent one
        // is not an error - it falls back to the column's own default.
        $this->postJson(self::ENDPOINT, ['rating' => 9, 'body' => 'short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['author_name', 'author_email', 'rating', 'body']);
    }

    public function test_a_review_sent_without_a_category_still_saves(): void
    {
        $product = Product::factory()->create();

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'author_name' => 'Asha Sharma',
            'author_email' => 'asha@example.test',
            'rating' => 5,
            'body' => 'It arrived quickly and does what it says.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('tbl_reviews', [
            'author_name' => 'Asha Sharma',
            'category' => 'quality',
        ]);
    }

    public function test_a_category_a_visitor_invents_is_still_refused(): void
    {
        // Optional is not the same as unchecked: the column is an enum, and a
        // value outside it would be a 500 rather than a validation message.
        $this->postJson(self::ENDPOINT, [
            'author_name' => 'Asha Sharma',
            'author_email' => 'asha@example.test',
            'category' => 'whatever-i-like',
            'rating' => 5,
            'body' => 'It arrived quickly and does what it says.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    public function test_a_review_of_a_draft_product_is_refused(): void
    {
        $draft = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'status' => 'draft',
        ]);

        $this->postJson(self::ENDPOINT, $this->payload(['product_id' => $draft->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_one_address_cannot_flood_the_queue(): void
    {
        $limit = (int) config('shop.reviews.max_per_day');

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        }

        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(429);

        $this->assertSame($limit, Review::query()->count());
    }

    /* --------------------------------------------------------------- pages */

    public function test_the_reviews_page_shows_only_approved_reviews(): void
    {
        Review::factory()->approved()->create(['body' => 'This one is published.']);
        Review::factory()->create(['body' => 'This one is still waiting.']);
        Review::factory()->rejected()->create(['body' => 'This one was turned down.']);

        $this->get('/reviews')
            ->assertOk()
            ->assertSee('This one is published.')
            ->assertDontSee('This one is still waiting.')
            ->assertDontSee('This one was turned down.');
    }

    public function test_the_product_page_shows_only_approved_reviews_for_that_product(): void
    {
        Review::factory()->approved()->create([
            'product_id' => $this->product->id,
            'body' => 'Published review of this product.',
        ]);
        Review::factory()->create([
            'product_id' => $this->product->id,
            'body' => 'Pending review of this product.',
        ]);
        Review::factory()->approved()->create(['body' => 'Approved review of the shop.']);

        $this->get('/product/'.$this->product->slug)
            ->assertOk()
            ->assertSee('Published review of this product.')
            ->assertDontSee('Pending review of this product.')
            ->assertDontSee('Approved review of the shop.')
            ->assertSee('Write a review');
    }

    public function test_the_featured_review_and_the_rating_breakdown_are_real(): void
    {
        Review::factory()->featured()->create([
            'rating' => 5,
            'author_name' => 'Grace Hopper',
            'body' => 'The one we pulled out at the top.',
        ]);
        Review::factory()->approved()->create(['rating' => 3]);

        // Two approved reviews at 5 and 3: the average is 4.0, and half the
        // reviews sit on each bar.
        $this->get('/reviews')
            ->assertOk()
            ->assertSee('Featured review')
            ->assertSee('The one we pulled out at the top.')
            ->assertSee('Grace Hopper')
            ->assertSee('4.0')
            ->assertSee('data-w="50"', false);
    }

    public function test_a_reviewer_email_is_never_rendered_on_a_public_page(): void
    {
        Review::factory()->approved()->create([
            'author_email' => 'private@example.com',
            'ip_address' => '203.0.113.7',
        ]);

        $this->get('/reviews')
            ->assertOk()
            ->assertDontSee('private@example.com')
            ->assertDontSee('203.0.113.7');
    }

    /* ------------------------------------------------------------- helpful */

    public function test_marking_a_review_helpful_counts_it(): void
    {
        $review = Review::factory()->approved()->create();

        $this->postJson(self::ENDPOINT."/{$review->id}/helpful")
            ->assertOk()
            ->assertJsonPath('data.helpful_count', 1);
    }

    public function test_a_pending_review_cannot_be_marked_helpful(): void
    {
        $review = Review::factory()->create();

        $this->postJson(self::ENDPOINT."/{$review->id}/helpful")->assertNotFound();
    }
}
