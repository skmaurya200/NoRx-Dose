<?php

namespace Tests\Feature\Storefront;

use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * What the journal tells a search engine about itself: the head tags, the
 * structured data, and the three files a crawler asks for that are not pages.
 */
class JournalSeoTest extends TestCase
{
    use RefreshDatabase;

    private BlogCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->category = BlogCategory::factory()->create([
            'name' => 'Ingredients',
            'slug' => 'ingredients',
        ]);
    }

    /**
     * @return array<string, mixed> the page's JSON-LD graph, decoded
     */
    private function graph(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<script type="application\/ld\+json">(.+?)<\/script>/s',
            $html,
            'the page carries no JSON-LD',
        );

        preg_match('/<script type="application\/ld\+json">(.+?)<\/script>/s', $html, $matches);

        return json_decode($matches[1], true) ?: [];
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>|null
     */
    private function node(array $graph, string $type): ?array
    {
        foreach ($graph['@graph'] ?? [] as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------ head tags */

    public function test_a_post_carries_its_own_title_and_description(): void
    {
        $post = BlogPost::factory()->for($this->category, 'category')->create([
            'title' => 'Reading a label',
            'meta_title' => 'How to read a supplement label',
            'meta_description' => 'The order is not random, and position tells you a lot.',
        ]);

        $this->get($post->url())
            ->assertOk()
            ->assertSee('<title>How to read a supplement label - NoRx Dose</title>', false)
            ->assertSee('name="description" content="The order is not random, and position tells you a lot."', false);
    }

    public function test_the_seo_title_falls_back_to_the_post_title(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Plain old title', 'meta_title' => null]);

        $this->get($post->url())->assertOk()->assertSee('<title>Plain old title - NoRx Dose</title>', false);
    }

    public function test_the_description_falls_back_to_the_excerpt(): void
    {
        $post = BlogPost::factory()->create([
            'meta_description' => null,
            'excerpt' => 'A short guide to the back of the bottle.',
        ]);

        $this->get($post->url())
            ->assertOk()
            ->assertSee('A short guide to the back of the bottle.', false);
    }

    /**
     * One address per page. Without it, a link with a tracking parameter is a
     * second copy of the post competing with the first.
     */
    public function test_a_post_declares_its_canonical_url(): void
    {
        $post = BlogPost::factory()->create();

        $this->get($post->url().'?utm_source=newsletter')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$post->url().'">', false);
    }

    public function test_a_post_carries_open_graph_article_tags(): void
    {
        $post = BlogPost::factory()->for($this->category, 'category')->create([
            'title' => 'Shareable post',
            'author_name' => 'The Aurum team',
        ]);

        $this->get($post->url())
            ->assertOk()
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('property="og:title" content="Shareable post - NoRx Dose"', false)
            ->assertSee('property="article:author" content="The Aurum team"', false)
            ->assertSee('property="article:section" content="Ingredients"', false)
            ->assertSee('property="article:published_time"', false)
            ->assertSee('property="article:modified_time"', false);
    }

    public function test_a_cover_image_becomes_the_share_image(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->post('/api/manager/blog/posts', [
            'title' => 'With a cover',
            'body' => '<p>Body text.</p>',
            'status' => 'published',
            'cover_alt' => 'Amber bottles on linen',
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 630),
        ])->assertStatus(201);

        $post = BlogPost::firstOrFail();

        $this->get($post->url())
            ->assertOk()
            ->assertSee('property="og:image" content="'.$post->coverUrl().'"', false)
            ->assertSee('property="og:image:alt" content="Amber bottles on linen"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false);
    }

    /* --------------------------------------------------------------- robots */

    public function test_an_ordinary_post_is_indexable(): void
    {
        $post = BlogPost::factory()->create();

        $this->get($post->url())
            ->assertOk()
            ->assertSee('name="robots" content="index, follow', false);
    }

    /**
     * Live and linkable, but deliberately kept out of search.
     */
    public function test_a_post_marked_noindex_says_so(): void
    {
        $post = BlogPost::factory()->create(['is_indexable' => false]);

        $this->get($post->url())
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false);
    }

    /**
     * A search results page has no content of its own and would compete with
     * the posts it links to.
     */
    public function test_a_search_results_page_is_noindex(): void
    {
        BlogPost::factory()->create(['title' => 'Findable']);

        $this->get('/blogs?q=findable')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_a_category_page_is_indexable_and_has_its_own_canonical(): void
    {
        BlogPost::factory()->for($this->category, 'category')->create();

        $this->get('/blogs?category=ingredients')
            ->assertOk()
            ->assertSee('name="robots" content="index, follow', false)
            ->assertSee('rel="canonical" href="'.route('blogs', ['category' => 'ingredients']).'"', false);
    }

    /**
     * Page two is its own page, not a duplicate of page one.
     */
    public function test_paginated_pages_carry_prev_and_next(): void
    {
        BlogPost::factory()->count(14)->create();

        $this->get('/blogs')->assertOk()->assertSee('rel="next"', false);

        $this->get('/blogs?page=2')
            ->assertOk()
            ->assertSee('rel="prev"', false)
            ->assertSee('rel="canonical" href="'.route('blogs').'?page=2"', false);
    }

    /* ------------------------------------------------------ structured data */

    public function test_a_post_publishes_blogposting_structured_data(): void
    {
        $post = BlogPost::factory()->for($this->category, 'category')->create([
            'title' => 'Structured post',
            'body' => '<p>One two three four five.</p>',
        ]);

        $article = $this->node($this->graph($post->url()), 'BlogPosting');

        $this->assertNotNull($article, 'no BlogPosting node');
        $this->assertSame('Structured post', $article['headline']);
        $this->assertSame($post->url(), $article['url']);
        $this->assertSame('Ingredients', $article['articleSection']);
        $this->assertSame(5, $article['wordCount']);
        $this->assertNotEmpty($article['datePublished']);
        $this->assertNotEmpty($article['dateModified']);
    }

    public function test_a_post_publishes_a_breadcrumb_trail(): void
    {
        $post = BlogPost::factory()->for($this->category, 'category')->create(['title' => 'Deep post']);

        $crumbs = $this->node($this->graph($post->url()), 'BreadcrumbList');

        $this->assertNotNull($crumbs, 'no BreadcrumbList node');
        $this->assertSame(
            ['Home', 'Journal', 'Ingredients', 'Deep post'],
            array_column($crumbs['itemListElement'], 'name'),
        );
    }

    public function test_every_page_carries_the_organisation(): void
    {
        $this->assertNotNull($this->node($this->graph('/'), 'Organization'));
        $this->assertNotNull($this->node($this->graph('/blogs'), 'Organization'));
    }

    public function test_the_listing_publishes_a_blog_node(): void
    {
        BlogPost::factory()->create(['title' => 'Listed post']);

        $blog = $this->node($this->graph('/blogs'), 'Blog');

        $this->assertNotNull($blog, 'no Blog node');
        $this->assertSame('Listed post', $blog['blogPost'][0]['headline']);
    }

    /* ------------------------------------------------------- crawler files */

    public function test_robots_txt_is_served_and_points_at_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /manager')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_the_sitemap_lists_published_posts(): void
    {
        $live = BlogPost::factory()->create();
        $draft = BlogPost::factory()->draft()->create();

        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(route('blog-details', $live->slug), false);
        $response->assertDontSee(route('blog-details', $draft->slug), false);
    }

    /**
     * A sitemap that lists a page marked noindex is a contradiction, and
     * search consoles report it as one.
     */
    public function test_the_sitemap_leaves_out_a_noindex_post(): void
    {
        $hidden = BlogPost::factory()->create(['is_indexable' => false]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee(route('blog-details', $hidden->slug), false);
    }

    public function test_the_sitemap_includes_the_static_pages(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('home'), false)
            ->assertSee(route('shop'), false)
            ->assertSee(route('blogs'), false);
    }

    public function test_the_feed_lists_the_newest_posts(): void
    {
        BlogPost::factory()->create(['title' => 'In the feed', 'published_at' => now()->subDay()]);
        BlogPost::factory()->draft()->create(['title' => 'Not in the feed']);

        $this->get('/blogs/feed.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('In the feed')
            ->assertDontSee('Not in the feed');
    }

    /**
     * The feed lives at a fixed path under /blogs, so it must not be mistaken
     * for a post whose slug happens to be "feed.xml".
     */
    public function test_the_feed_route_wins_over_the_post_route(): void
    {
        $this->get('/blogs/feed.xml')->assertOk()->assertSee('<rss', false);
    }

    public function test_every_page_advertises_the_feed(): void
    {
        $this->get('/blogs')
            ->assertOk()
            ->assertSee('type="application/rss+xml"', false);
    }
}
