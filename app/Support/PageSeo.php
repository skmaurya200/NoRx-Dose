<?php

namespace App\Support;

use App\Support\Settings\Site;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/**
 * What one page tells a search engine and a share preview about itself.
 *
 * Built in the controller and rendered once by components/seo.blade.php, so
 * every page emits the same set of tags in the same order and no template has
 * to remember the incantation for an Open Graph article.
 *
 * A page that sets nothing still gets a canonical URL, a description and the
 * site's Organization schema - the defaults are the point, because the tag a
 * page forgets is the one that costs it.
 */
class PageSeo implements Arrayable
{
    public ?string $title = null;

    public ?string $description = null;

    public ?string $canonical = null;

    /** Absolute URL of the share image. */
    public ?string $image = null;

    public ?string $imageAlt = null;

    /** "website" or "article". */
    public string $type = 'website';

    public bool $noindex = false;

    public ?string $publishedAt = null;

    public ?string $modifiedAt = null;

    public ?string $author = null;

    public ?string $section = null;

    public ?string $prev = null;

    public ?string $next = null;

    /** @var array<int, array<string, mixed>> JSON-LD graph nodes. */
    public array $schema = [];

    public static function make(): self
    {
        return new self;
    }

    /* --------------------------------------------------------------- setters */

    public function title(?string $title): self
    {
        $this->title = $this->tidy($title, 70);

        return $this;
    }

    /**
     * Descriptions are trimmed at 160 characters on a word boundary, which is
     * roughly where Google stops rendering one. Cutting here rather than
     * letting the engine do it means the sentence ends where we chose.
     */
    public function description(?string $description): self
    {
        $this->description = $this->tidy($description, 160);

        return $this;
    }

    public function canonical(?string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function image(?string $url, ?string $alt = null): self
    {
        $this->image = $url;
        $this->imageAlt = $alt;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Keeps a page out of the index. Used for anything thin or duplicated - a
     * search results page, say, which has no content of its own and would
     * otherwise compete with the pages it links to.
     */
    public function noindex(bool $noindex = true): self
    {
        $this->noindex = $noindex;

        return $this;
    }

    public function article(?string $published, ?string $modified, ?string $author, ?string $section = null): self
    {
        $this->type = 'article';
        $this->publishedAt = $published;
        $this->modifiedAt = $modified;
        $this->author = $author;
        $this->section = $section;

        return $this;
    }

    /**
     * rel=prev / rel=next. Deprecated as an indexing signal by Google and
     * still read by other engines, and harmless either way.
     */
    public function paginate(?string $prev, ?string $next): self
    {
        $this->prev = $prev;
        $this->next = $next;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function schema(array $node): self
    {
        $this->schema[] = $node;

        return $this;
    }

    /* --------------------------------------------------------------- output */

    public function resolvedTitle(?string $fallback = null): string
    {
        $title = $this->title ?: $fallback ?: Site::name();
        $site = Site::name();

        // Never "NoRx Dose - NoRx Dose", and never a suffix on a
        // title that already carries the brand.
        if ($title === $site || str_contains($title, $site)) {
            return $title;
        }

        return $title.' - '.$site;
    }

    public function resolvedDescription(): string
    {
        return $this->description ?: Site::description();
    }

    public function resolvedCanonical(): string
    {
        // The current URL without its query string: two URLs that differ only
        // by a tracking parameter are one page, and saying so here is what
        // stops them being indexed twice.
        return $this->canonical ?: url()->current();
    }

    public function resolvedImage(): ?string
    {
        return $this->image ?? Site::shareImage();
    }

    /**
     * The JSON-LD graph: whatever the page added, plus the site-wide
     * Organization and WebSite nodes every page should carry.
     *
     * @return array<string, mixed>
     */
    public function graph(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_merge([$this->organisation(), $this->website()], $this->schema),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organisation(): array
    {
        $node = [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => Site::name(),
            'url' => url('/'),
            'description' => Site::tagline(),
        ];

        $settings = Site::settings();

        foreach (['email' => 'contact.email', 'telephone' => 'contact.phone'] as $key => $path) {
            if ($settings->has($path)) {
                $node[$key] = $settings->text($path);
            }
        }

        if ($settings->has('contact.address')) {
            $node['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $settings->text('contact.address'),
            ];
        }

        $profiles = Site::profiles();

        if ($profiles !== []) {
            $node['sameAs'] = $profiles;
        }

        $logo = Site::logo();

        if ($logo) {
            $node['logo'] = $logo;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => Site::name(),
            'url' => url('/'),
            'publisher' => ['@id' => url('/').'#organization'],
        ];
    }

    /**
     * A BreadcrumbList from a plain label => url map, for pages that want one.
     *
     * @param  array<string, string>  $trail
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $trail): array
    {
        $items = [];
        $position = 1;

        foreach ($trail as $name => $url) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url,
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->resolvedTitle(),
            'description' => $this->resolvedDescription(),
            'canonical' => $this->resolvedCanonical(),
            'image' => $this->resolvedImage(),
            'type' => $this->type,
            'noindex' => $this->noindex,
        ];
    }

    /* -------------------------------------------------------------- private */

    /**
     * Collapses whitespace, strips any markup that came from a body field and
     * trims on a word boundary.
     */
    private function tidy(?string $value, int $limit): ?string
    {
        $value = $value instanceof Htmlable ? $value->toHtml() : $value;
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit, '…');
    }
}
