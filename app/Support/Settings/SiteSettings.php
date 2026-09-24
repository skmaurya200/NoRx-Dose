<?php

namespace App\Support\Settings;

use Illuminate\Support\HtmlString;

/**
 * The site-wide settings, as a template reads them.
 *
 * Composed into every view as $site, so the header asks for
 * $site->text('brand.name') and gets either what the operator saved or the
 * words the site was built with. There is no third outcome.
 *
 * The reading half of the interface matches App\Support\Content\PageContent
 * deliberately: the Settings form reuses the Pages module's field partials,
 * and those partials call saved(), rawImage() and image() on whatever bag they
 * are handed.
 */
class SiteSettings
{
    /**
     * @param  array<string, string|null>  $values  saved values, "group.item" => value
     * @param  array<string, string|null>  $defaults
     */
    public function __construct(
        private readonly array $values = [],
        private readonly array $defaults = [],
    ) {}

    /**
     * @param  array<string, string|null>  $values
     */
    public static function make(array $values = []): self
    {
        return new self($values, SettingSchema::defaults());
    }

    public function text(string $path, ?string $fallback = null): string
    {
        return $this->raw($path) ?? (string) ($fallback ?? '');
    }

    public function html(string $path, ?string $fallback = null): HtmlString
    {
        return new HtmlString($this->text($path, $fallback));
    }

    /**
     * The URL of an uploaded picture, or null when nothing has been uploaded.
     * Null rather than a placeholder: a logo the operator has not set means
     * "draw the ✦ mark the design ships with", not "draw a broken image".
     */
    public function image(string $path): ?string
    {
        $value = $this->raw($path);

        return $value ? asset($value) : null;
    }

    /**
     * The stored path, unresolved. The panel needs this to decide whether to
     * offer a "remove" control.
     */
    public function rawImage(string $path): ?string
    {
        return $this->raw($path);
    }

    /**
     * Only what an operator saved, ignoring the default - so the panel keeps
     * an untouched field empty, with the original wording in the placeholder
     * where it can be seen and restored to.
     */
    public function saved(string $path): ?string
    {
        $value = $this->values[$path] ?? null;

        return $value === '' ? null : $value;
    }

    public function has(string $path): bool
    {
        return $this->raw($path) !== null;
    }

    /**
     * The social links that actually point somewhere, in the order the design
     * draws them. A blank one is left out rather than rendered as a dead link.
     *
     * @return array<int, array{key: string, label: string, url: string}>
     */
    public function socialLinks(): array
    {
        $links = [];

        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X',
            'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'] as $key => $label) {
            $url = $this->raw('social.'.$key);

            if ($url !== null) {
                $links[] = ['key' => $key, 'label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * Saved value, else schema default, else null. A blank string counts as
     * "not set", which is what makes clearing a field in the panel restore the
     * original copy rather than leave a hole in the page.
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
