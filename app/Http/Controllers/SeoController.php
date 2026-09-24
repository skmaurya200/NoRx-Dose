<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The three files a crawler asks for that are not pages: robots.txt,
 * sitemap.xml and the journal's RSS feed.
 *
 * Served by routes rather than written to public/ so they follow the database
 * and the configured APP_URL instead of whatever was true when someone last
 * exported them by hand.
 */
class SeoController extends Controller
{
    /**
     * GET /robots.txt
     *
     * A physical public/robots.txt would take precedence over this route on
     * most web servers, which is why that file was removed.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            '',
            // Nothing here is content. The panel is behind auth anyway, but
            // saying so keeps it out of crawl budget and out of any accidental
            // index of a login page.
            'Disallow: /manager',
            'Disallow: /manager/',
            'Disallow: /api/',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /order/',
            '',
            // Search results are thin by nature and would compete with the
            // pages they link to.
            'Disallow: /*?q=',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * GET /sitemap.xml
     *
     * Cached: it walks three tables, and crawlers ask far more often than the
     * content changes.
     */
    public function sitemap(): Response
    {
        $minutes = (int) config('seo.sitemap.cache_minutes', 60);

        $xml = $minutes > 0
            ? Cache::remember('seo.sitemap', now()->addMinutes($minutes), fn () => $this->buildSitemap())
            : $this->buildSitemap();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * GET /blogs/feed.xml
     *
     * Declared before /blogs/{slug} in the route file, or "feed.xml" would be
     * read as a post slug.
     */
    public function feed(): Response
    {
        $minutes = (int) config('seo.feed.cache_minutes', 30);

        $xml = $minutes > 0
            ? Cache::remember('seo.feed', now()->addMinutes($minutes), fn () => $this->buildFeed())
            : $this->buildFeed();

        return response($xml, 200)->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /* -------------------------------------------------------------- sitemap */

    private function buildSitemap(): string
    {
        $urls = [];

        // Static pages, with a rough sense of how often each one moves.
        foreach ([
            ['home', 'daily', '1.0'],
            ['shop', 'daily', '0.9'],
            ['all-products', 'daily', '0.8'],
            ['blogs', 'daily', '0.8'],
            ['reviews', 'weekly', '0.6'],
            ['about', 'monthly', '0.5'],
            ['faq', 'monthly', '0.5'],
            ['shipping-policy', 'yearly', '0.3'],
        ] as [$name, $frequency, $priority]) {
            $urls[] = [
                'loc' => route($name),
                'changefreq' => $frequency,
                'priority' => $priority,
            ];
        }

        $limit = (int) config('seo.sitemap.max_per_section', 2000);

        // Journal posts. An operator can keep one out with the noindex switch,
        // and a sitemap that lists a noindex page is a contradiction.
        BlogPost::query()
            ->published()
            ->where('is_indexable', true)
            ->newest()
            ->limit($limit)
            ->get(['slug', 'updated_at'])
            ->each(function (BlogPost $post) use (&$urls) {
                $urls[] = [
                    'loc' => route('blog-details', $post->slug),
                    'lastmod' => $post->updated_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ];
            });

        // Category listings, but only the ones with something on them.
        BlogCategory::query()
            ->active()
            ->whereHas('posts', fn (Builder $q) => $q->published())
            ->ordered()
            ->limit($limit)
            ->get(['slug'])
            ->each(function (BlogCategory $category) use (&$urls) {
                $urls[] = [
                    'loc' => route('blogs', ['category' => $category->slug]),
                    'changefreq' => 'weekly',
                    'priority' => '0.5',
                ];
            });

        Product::query()
            ->published()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['slug', 'updated_at'])
            ->each(function (Product $product) use (&$urls) {
                $urls[] = [
                    'loc' => route('product', $product->slug),
                    'lastmod' => $product->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

        $body = '';

        foreach ($urls as $url) {
            $body .= '  <url>'."\n";
            $body .= '    <loc>'.e($url['loc']).'</loc>'."\n";

            if (! empty($url['lastmod'])) {
                $body .= '    <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
            }

            $body .= '    <changefreq>'.$url['changefreq'].'</changefreq>'."\n";
            $body .= '    <priority>'.$url['priority'].'</priority>'."\n";
            $body .= '  </url>'."\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$body
            .'</urlset>'."\n";
    }

    /* ----------------------------------------------------------------- feed */

    private function buildFeed(): string
    {
        $posts = BlogPost::query()
            ->published()
            ->with('category:id,name')
            ->newest()
            ->limit((int) config('seo.feed.limit', 20))
            ->get();

        $items = '';

        foreach ($posts as $post) {
            $url = route('blog-details', $post->slug);

            $items .= '    <item>'."\n";
            $items .= '      <title>'.e($post->title).'</title>'."\n";
            $items .= '      <link>'.e($url).'</link>'."\n";
            // A permanent identifier for the item, which is how a reader knows
            // it has seen this post before even if the title is edited.
            $items .= '      <guid isPermaLink="true">'.e($url).'</guid>'."\n";
            $items .= '      <description>'.e($post->excerptLabel(300)).'</description>'."\n";
            $items .= '      <pubDate>'.($post->published_at ?? $post->created_at)->toRfc2822String().'</pubDate>'."\n";

            if ($post->category) {
                $items .= '      <category>'.e($post->category->name).'</category>'."\n";
            }

            $items .= '    </item>'."\n";
        }

        $updated = $posts->first()?->published_at ?? now();

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n"
            .'  <channel>'."\n"
            .'    <title>'.e(config('seo.site_name').' journal').'</title>'."\n"
            .'    <link>'.e(route('blogs')).'</link>'."\n"
            .'    <description>'.e(config('seo.default_description')).'</description>'."\n"
            .'    <language>en</language>'."\n"
            .'    <lastBuildDate>'.$updated->toRfc2822String().'</lastBuildDate>'."\n"
            .'    <atom:link href="'.e(route('blogs.feed')).'" rel="self" type="application/rss+xml"/>'."\n"
            .$items
            .'  </channel>'."\n"
            .'</rss>'."\n";
    }
}
