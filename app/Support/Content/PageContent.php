<?php

namespace App\Support\Content;

use App\Support\DefaultImage;
use App\Support\PageSeo;
use Illuminate\Support\HtmlString;

/**
 * The editable copy for one page, as a template reads it.
 *
 * Composed into the storefront views as $content, so a template asks for
 * $content->text('hero.tag') and gets either what the operator saved or the
 * words the page was written with. There is no third outcome: a field that has
 * never been edited, or was saved empty, falls back to its schema default, so
 * the design cannot be emptied out by accident.
 */
class PageContent
{
    /**
     * @param  array<string, string|null>  $values  saved values, "section.field" => value
     * @param  array<string, string|null>  $defaults
     */
    public function __construct(
        private readonly string $pageKey,
        private readonly array $values = [],
        private readonly array $defaults = [],
    ) {}

    public static function forPage(string $pageKey, array $values = []): self
    {
        return new self($pageKey, $values, PageSchema::defaults($pageKey));
    }

    /**
     * An empty bag, for a view composed before anything is set up. Every
     * lookup falls through to the argument passed at the call site.
     */
    public static function blank(): self
    {
        return new self('');
    }

    public function pageKey(): string
    {
        return $this->pageKey;
    }

    /**
     * Plain text, escaped by Blade at the call site as usual.
     *
     * The $fallback argument is what the template would have printed if this
     * module did not exist. It is the last resort, behind the saved value and
     * the schema default, which means a template stays readable on its own.
     */
    public function text(string $path, ?string $fallback = null): string
    {
        $value = $this->raw($path);

        return $value ?? (string) ($fallback ?? '');
    }

    /**
     * A heading, printed unescaped.
     *
     * Only ever holds <em>, <strong>, <br> and their kin: the value was run
     * through HtmlSanitizer::inline() before it was stored, so the italic word
     * the design calls for survives and nothing else can.
     */
    public function html(string $path, ?string $fallback = null): HtmlString
    {
        return new HtmlString($this->text($path, $fallback));
    }

    /**
     * The URL of an uploaded image.
     *
     * Naming a $slot returns one of App\Support\DefaultImage's drawings when
     * nothing has been uploaded, so a template is a single <img> rather than
     * an if/else around an empty box. Without a slot it returns null, for the
     * few places that genuinely want to draw nothing.
     */
    public function image(string $path, ?string $slot = null): ?string
    {
        $value = $this->raw($path);

        if ($value) {
            return asset($value);
        }

        return $slot ? DefaultImage::for($slot) : null;
    }

    /**
     * Only what an operator saved, ignoring the schema default. The panel
     * needs this to keep an untouched field empty, with the original wording
     * in the placeholder where it can be seen and restored to.
     */
    public function saved(string $path): ?string
    {
        $value = $this->values[$path] ?? null;

        return $value === '' ? null : $value;
    }

    /**
     * The rows of a repeater field - the home page's FAQ, for instance.
     *
     * Saved rows are stored as JSON in the same column as everything else;
     * unsaved, the schema's own list comes back, so the section renders as it
     * was designed until someone edits it.
     *
     * @return array<int, array<string, string>>
     */
    public function items(string $path): array
    {
        $saved = $this->values[$path] ?? null;

        if (is_string($saved) && $saved !== '') {
            $decoded = json_decode($saved, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $default = $this->defaults[$path] ?? [];

        return is_array($default) ? $default : [];
    }

    /**
     * Lays this page's own meta title, description and share image over
     * whatever the controller built.
     *
     * saved(), not text(): the SEO fields default to blank on purpose, and
     * blank means "use the site defaults from Settings". Only a value an
     * operator actually typed overrides the page - and when they have typed
     * one, it wins over the controller, because it is the more deliberate of
     * the two.
     */
    public function applySeo(PageSeo $seo): PageSeo
    {
        if ($this->saved('seo.title') !== null) {
            $seo->title($this->saved('seo.title'));
        }

        if ($this->saved('seo.description') !== null) {
            $seo->description($this->saved('seo.description'));
        }

        $image = $this->rawImage('seo.share_image');

        if ($image !== null) {
            $seo->image(asset($image));
        }

        return $seo;
    }

    public function has(string $path): bool
    {
        return $this->raw($path) !== null;
    }

    /**
     * The stored path of an image, unresolved. The panel needs this to decide
     * whether to show a "remove" control.
     */
    public function rawImage(string $path): ?string
    {
        return $this->raw($path);
    }

    /**
     * Saved value, else schema default, else null. Blank strings count as
     * "not set" so clearing a field in the panel restores the original copy
     * rather than leaving a hole in the page.
     */
    private function raw(string $path): ?string
    {
        foreach ([$this->values, $this->defaults] as $source) {
            $value = $source[$path] ?? null;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
