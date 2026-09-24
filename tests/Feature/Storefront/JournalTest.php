<?php

namespace Tests\Feature\Storefront;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The journal as a visitor sees it: the home page rail, /blogs and a post.
 */
class JournalTest extends TestCase
{
    use RefreshDatabase;

    private BlogCategory $ingredients;

    private BlogCategory $routines;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ingredients = BlogCategory::factory()->create(['name' => 'Ingredients', 'slug' => 'ingredients']);
        $this->routines = BlogCategory::factory()->create(['name' => 'Routines', 'slug' => 'routines']);
    }

    /* -------------------------------------------------------------- listing */

    public function test_the_journal_lists_published_posts(): void
    {
        BlogPost::factory()->for($this->ingredients, 'category')->create(['title' => 'A published post']);

        $this->get('/blogs')
            ->assertOk()
            ->assertSee('A published post')
            ->assertSee('Ingredients');
    }

    public function test_drafts_and_archived_posts_are_not_listed(): void
    {
        BlogPost::factory()->draft()->create(['title' => 'Unfinished']);
        BlogPost::factory()->archived()->create(['title' => 'Retired']);

        $response = $this->get('/blogs')->assertOk();

        $response->assertDontSee('Unfinished');
        $response->assertDontSee('Retired');
    }

    /**
     * A post dated in the future is written and waiting, not published.
     */
    public function test_a_scheduled_post_is_not_listed_yet(): void
    {
        BlogPost::factory()->scheduled()->create(['title' => 'Next week']);

        $this->get('/blogs')->assertOk()->assertDontSee('Next week');
    }

    public function test_the_featured_post_leads_the_page(): void
    {
        BlogPost::factory()->create(['title' => 'An ordinary post']);
        BlogPost::factory()->featured()->create(['title' => 'The lead post']);

        $this->get('/blogs')
            ->assertOk()
            ->assertSee('The lead post')
            ->assertSee('Read the post')
            ->assertSeeInOrder(['class="journal__listing"', 'class="feat"', 'class="journal__grid"'], false);
    }

    /**
     * Nothing is listed twice: the featured post is excluded from the grid
     * that follows it.
     */
    public function test_the_featured_post_is_not_repeated_in_the_grid(): void
    {
        $post = BlogPost::factory()->featured()->create(['title' => 'Only once please']);

        $html = $this->get('/blogs')->assertOk()->getContent();

        // Counted on the card markup rather than on the title text: the title
        // legitimately appears again in the page's JSON-LD.
        $grid = str_contains($html, '<div class="journal__grid"')
            ? substr($html, strpos($html, '<div class="journal__grid"'))
            : '';

        $this->assertStringContainsString('Only once please', $html, 'the featured post is missing');
        $this->assertStringNotContainsString('Only once please</a></h3>', $grid);
        $this->assertSame(1, substr_count($html, '<h2><a href="'.$post->url().'">'));
    }

    /* ------------------------------------------------------------ filtering */

    public function test_a_category_filter_narrows_the_list(): void
    {
        BlogPost::factory()->for($this->ingredients, 'category')->create(['title' => 'About actives']);
        BlogPost::factory()->for($this->routines, 'category')->create(['title' => 'About habits']);

        $this->get('/blogs?category=ingredients')
            ->assertOk()
            ->assertSee('About actives')
            ->assertDontSee('About habits');
    }

    public function test_search_matches_the_title_and_the_body(): void
    {
        BlogPost::factory()->create(['title' => 'Magnesium explained', 'body' => '<p>Nothing special.</p>']);
        BlogPost::factory()->create(['title' => 'Something else', 'body' => '<p>A word about collagen.</p>']);

        $this->get('/blogs?q=magnesium')->assertOk()
            ->assertSee('Magnesium explained')->assertDontSee('Something else');

        $this->get('/blogs?q=collagen')->assertOk()
            ->assertSee('Something else')->assertDontSee('Magnesium explained');
    }

    public function test_a_search_with_no_matches_says_so(): void
    {
        BlogPost::factory()->create(['title' => 'A post']);

        $this->get('/blogs?q=nothingmatchesthis')
            ->assertOk()
            ->assertSee('Nothing here matches that.');
    }

    /**
     * The chips only offer categories that have something behind them.
     */
    public function test_an_empty_category_gets_no_chip(): void
    {
        BlogPost::factory()->for($this->ingredients, 'category')->create();
        BlogCategory::factory()->create(['name' => 'Nothing Here', 'slug' => 'nothing-here']);

        $this->get('/blogs')->assertOk()->assertDontSee('Nothing Here');
    }

    /**
     * Hiding a category removes the way in, not the posts: they stay published
     * and their cards still say what they are, there is just no chip to filter
     * by any more.
     */
    public function test_a_hidden_category_gets_no_chip(): void
    {
        $hidden = BlogCategory::factory()->hidden()->create([
            'name' => 'Internal Notes',
            'slug' => 'internal-notes',
        ]);
        BlogPost::factory()->for($hidden, 'category')->create(['title' => 'Still readable']);

        $this->get('/blogs')
            ->assertOk()
            ->assertSee('Still readable')
            ->assertDontSee('category=internal-notes', false);
    }

    public function test_the_listing_is_paginated(): void
    {
        BlogPost::factory()->count(14)->create();

        $this->get('/blogs')->assertOk()->assertSee('?page=2', false);
    }

    /* ------------------------------------------------------------ one post */

    public function test_a_post_is_reachable_by_its_slug(): void
    {
        $post = BlogPost::factory()->for($this->ingredients, 'category')->create([
            'title' => 'Reading a label',
            'body' => '<p>The order is not random.</p>',
            'takeaways' => ['Position tells you a lot.'],
        ]);

        $this->get('/blogs/'.$post->slug)
            ->assertOk()
            ->assertSee('Reading a label')
            ->assertSee('The order is not random.')
            ->assertSee('Position tells you a lot.')
            ->assertSee('The short version');
    }

    /**
     * The body is the one place the storefront prints stored markup, so it has
     * to actually render as markup rather than as escaped text.
     */
    public function test_the_body_is_rendered_as_html(): void
    {
        $post = BlogPost::factory()->create([
            'body' => '<h2>A heading</h2><p>A <strong>strong</strong> word.</p>',
        ]);

        $this->get('/blogs/'.$post->slug)
            ->assertOk()
            ->assertSee('<h2>A heading</h2>', false)
            ->assertSee('<strong>strong</strong>', false);
    }

    public function test_a_draft_post_is_a_404(): void
    {
        $post = BlogPost::factory()->draft()->create();

        $this->get('/blogs/'.$post->slug)->assertNotFound();
    }

    public function test_a_scheduled_post_is_a_404_until_its_date(): void
    {
        $post = BlogPost::factory()->scheduled()->create();

        $this->get('/blogs/'.$post->slug)->assertNotFound();
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->get('/blogs/no-such-post')->assertNotFound();
    }

    public function test_a_post_without_takeaways_drops_the_summary_block(): void
    {
        $post = BlogPost::factory()->create(['takeaways' => null]);

        $this->get('/blogs/'.$post->slug)->assertOk()->assertDontSee('The short version');
    }

    public function test_reading_a_post_counts_a_view(): void
    {
        $post = BlogPost::factory()->create(['views_count' => 0]);

        $this->get('/blogs/'.$post->slug)->assertOk();
        $this->get('/blogs/'.$post->slug)->assertOk();

        $this->assertSame(2, $post->fresh()->views_count);
    }

    /* --------------------------------------------------------------- related */

    public function test_related_posts_prefer_the_same_category(): void
    {
        $post = BlogPost::factory()->for($this->ingredients, 'category')->create(['title' => 'The post itself']);
        BlogPost::factory()->for($this->ingredients, 'category')->create(['title' => 'A sibling post']);
        BlogPost::factory()->for($this->routines, 'category')->create(['title' => 'An unrelated post']);

        $this->get('/blogs/'.$post->slug)
            ->assertOk()
            ->assertSee('A sibling post');
    }

    /**
     * A lone post has nothing to suggest, so the block is dropped rather than
     * left as an empty heading.
     */
    public function test_the_related_block_disappears_when_there_is_nothing_to_show(): void
    {
        $post = BlogPost::factory()->create();

        $this->get('/blogs/'.$post->slug)
            ->assertOk()
            ->assertDontSee('More from the journal');
    }

    /* ------------------------------------------------------------ home page */

    public function test_the_home_page_shows_the_newest_posts(): void
    {
        BlogPost::factory()->create(['title' => 'Oldest one', 'published_at' => now()->subYear()]);
        BlogPost::factory()->create(['title' => 'Newest one', 'published_at' => now()->subDay()]);

        $this->get('/')->assertOk()->assertSee('Newest one');
    }

    public function test_the_home_page_survives_an_empty_journal(): void
    {
        $this->get('/')->assertOk()->assertSee('The first post is on its way.');
    }

    public function test_the_home_page_never_shows_a_draft(): void
    {
        BlogPost::factory()->draft()->create(['title' => 'Unfinished business']);

        $this->get('/')->assertOk()->assertDontSee('Unfinished business');
    }
}
