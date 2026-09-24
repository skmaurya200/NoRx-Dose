<?php

namespace App\Services\Content;

use App\Models\PageContent as PageContentRow;
use App\Support\Content\PageContent;
use App\Support\Content\PageSchema;
use App\Support\HtmlSanitizer;
use App\Support\PublicUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes the editable copy on the storefront's fixed pages.
 *
 * Every storefront page load asks for a page's values, so they are cached
 * forever and forgotten on save rather than given a timeout - the data changes
 * when an operator changes it and at no other moment.
 */
class PageContentService
{
    private const UPLOAD_BUCKET = 'pages';

    private const CACHE_PREFIX = 'page-content.';

    /**
     * The bag a template reads, with the schema defaults behind it.
     */
    public function forPage(string $pageKey): PageContent
    {
        if (! PageSchema::exists($pageKey)) {
            return PageContent::blank();
        }

        return PageContent::forPage($pageKey, $this->values($pageKey));
    }

    /**
     * Saved values only, as "section.field" => value.
     *
     * @return array<string, string|null>
     */
    public function values(string $pageKey): array
    {
        return Cache::rememberForever(
            self::CACHE_PREFIX.$pageKey,
            fn () => PageContentRow::query()
                ->where('page_key', $pageKey)
                ->get()
                ->mapWithKeys(fn (PageContentRow $row) => [$row->path() => $row->value])
                ->all(),
        );
    }

    /* ---------------------------------------------------------------- write */

    /**
     * Saves the fields a form sent, and the images it uploaded.
     *
     * Only paths named in the schema are touched, so a crafted payload cannot
     * invent a field. A field submitted empty is deleted rather than stored as
     * an empty string, which is what makes "clear it to restore the original
     * wording" true rather than approximately true.
     *
     * @param  array<string, string|null>  $fields  "section.field" => value
     * @param  array<string, UploadedFile>  $images  "section.field" => file
     * @param  array<int, string>  $removeImages  paths to clear
     * @param  array<string, array<int, array<string, string>>>  $repeaters  "section.field" => rows
     */
    public function save(
        string $pageKey,
        array $fields,
        array $images = [],
        array $removeImages = [],
        array $repeaters = [],
    ): void {
        $schema = PageSchema::fields($pageKey);
        $existing = $this->values($pageKey);

        DB::transaction(function () use ($pageKey, $fields, $images, $removeImages, $repeaters, $schema, $existing) {
            foreach ($schema as $path => $field) {
                $type = $field['type'] ?? 'text';

                if ($type === 'image') {
                    $this->saveImage($pageKey, $path, $images[$path] ?? null,
                        in_array($path, $removeImages, true), $existing[$path] ?? null);

                    continue;
                }

                if ($type === 'repeater') {
                    if (array_key_exists($path, $repeaters)) {
                        $this->put($pageKey, $path, $this->normaliseRows($repeaters[$path], $field));
                    }

                    continue;
                }

                // A field the form did not send is left alone. That keeps a
                // partial save - one section's form, say - from wiping the
                // rest of the page.
                if (! array_key_exists($path, $fields)) {
                    continue;
                }

                $this->put($pageKey, $path, $this->normalise($fields[$path], $field));
            }
        });

        $this->forget($pageKey);
    }

    /**
     * Rows of a repeater, cleaned and stored as JSON.
     *
     * A row is kept only if its first sub-field has something in it - the
     * question, for the FAQ - because an answer with no question is not a row
     * anyone meant to add. Order is whatever the form posted, which is the
     * order the operator dragged them into.
     *
     * @param  mixed  $rows
     * @param  array<string, mixed>  $field
     */
    private function normaliseRows($rows, array $field): ?string
    {
        if (! is_array($rows)) {
            return null;
        }

        $subFields = array_keys($field['fields'] ?? []);
        $primary = $subFields[0] ?? null;
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $entry = [];

            foreach ($subFields as $key) {
                // Copy, never markup: these are printed escaped.
                $entry[$key] = trim(strip_tags((string) ($row[$key] ?? '')));
            }

            if ($primary === null || $entry[$primary] === '') {
                continue;
            }

            $clean[] = $entry;
        }

        if ($clean === []) {
            return null;
        }

        $clean = array_slice($clean, 0, (int) ($field['max'] ?? 30));

        return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Cleans a value for its field type on the way in, so nothing downstream
     * has to filter it again.
     *
     * @param  array<string, mixed>  $field
     */
    private function normalise(?string $value, array $field): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return match ($field['type'] ?? 'text') {
            // A heading keeps its <em> and its <br> and loses everything else.
            'inline' => HtmlSanitizer::inline($value) ?: null,

            // Prose and one-liners are stored as text and escaped on output.
            default => strip_tags($value),
        };
    }

    private function saveImage(
        string $pageKey,
        string $path,
        ?UploadedFile $file,
        bool $remove,
        ?string $current,
    ): void {
        if ($file !== null) {
            $this->put($pageKey, $path, PublicUpload::replace($file, self::UPLOAD_BUCKET, $current));

            return;
        }

        if ($remove && $current) {
            PublicUpload::delete($current, self::UPLOAD_BUCKET);
            $this->put($pageKey, $path, null);
        }
    }

    /**
     * Writes one field, or deletes the row when the value is empty - the table
     * only holds what has actually been changed.
     */
    private function put(string $pageKey, string $path, ?string $value): void
    {
        [$section, $field] = explode('.', $path, 2);

        $keys = ['page_key' => $pageKey, 'section_key' => $section, 'field_key' => $field];

        if ($value === null || $value === '') {
            PageContentRow::query()->where($keys)->delete();

            return;
        }

        PageContentRow::query()->updateOrCreate($keys, ['value' => $value]);
    }

    public function forget(string $pageKey): void
    {
        Cache::forget(self::CACHE_PREFIX.$pageKey);
    }

    /**
     * How much of a page has been edited, for the list screen. A page with
     * nothing saved is still a working page - it is showing its defaults.
     */
    public function editedCount(string $pageKey): int
    {
        return count(array_filter($this->values($pageKey), fn ($value) => $value !== null && $value !== ''));
    }
}
