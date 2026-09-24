<?php

namespace App\Http\Requests\APIs\Page;

use App\Support\Content\PageSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The Pages form, validated against the schema rather than against a fixed
 * list of rules.
 *
 * The form is generated, so its rules are too: a field the schema does not
 * name is simply never read, which is what stops a crafted payload writing
 * copy into a page that has no such slot.
 */
class UpdatePageContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $bucket = config('admin.uploads.pages');

        $rules = [
            'fields' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'repeaters' => ['nullable', 'array'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['nullable', 'string'],
        ];

        foreach (PageSchema::fields($this->pageKey()) as $path => $field) {
            $type = $field['type'] ?? 'text';

            // The input name, not the schema path: a dot would be read as
            // Laravel's array separator and the rule would never match.
            $name = PageSchema::inputName($path);

            if ($type === 'image') {
                $rules['images.'.$name] = [
                    'nullable', 'image',
                    'mimes:'.implode(',', $bucket['mimes']),
                    'max:'.$bucket['max_kb'],
                ];

                continue;
            }

            if ($type === 'repeater') {
                $rules['repeaters.'.$name] = ['nullable', 'array', 'max:'.($field['max'] ?? 30)];

                foreach ($field['fields'] ?? [] as $subKey => $sub) {
                    $rules['repeaters.'.$name.'.*.'.$subKey] = ($sub['type'] ?? 'text') === 'textarea'
                        ? ['nullable', 'string', 'max:2000']
                        : ['nullable', 'string', 'max:500'];
                }

                continue;
            }

            // Generous on length and strict on nothing else: this is copy, and
            // the only real ceiling is what the design can hold.
            $rules['fields.'.$name] = match ($type) {
                'textarea' => ['nullable', 'string', 'max:2000'],
                'url' => ['nullable', 'string', 'max:255'],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        return $rules;
    }

    public function pageKey(): string
    {
        return (string) $this->route('page');
    }

    /**
     * Only the paths the schema knows about, keyed the way the schema keys
     * them - so an extra key in the payload is dropped rather than stored.
     *
     * @return array<string, string|null>
     */
    public function fields(): array
    {
        return $this->mapToPaths((array) $this->input('fields', []));
    }

    /**
     * @return array<string, UploadedFile>
     */
    public function images(): array
    {
        return array_filter($this->mapToPaths((array) $this->file('images', [])));
    }

    /**
     * Rows for every repeater the form sent.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public function repeaters(): array
    {
        return $this->mapToPaths((array) $this->input('repeaters', []));
    }

    /**
     * @return array<int, string>
     */
    public function removeImages(): array
    {
        $paths = array_keys(PageSchema::fields($this->pageKey()));

        // Sent as values rather than keys, so they arrive as schema paths and
        // only need checking against the schema.
        $submitted = array_filter(
            (array) $this->input('remove_images', []),
            fn (mixed $path): bool => is_string($path) && $path !== '',
        );

        return array_values(array_intersect($submitted, $paths));
    }

    /**
     * Turns "hero__title" keys back into "hero.title" and drops anything the
     * schema does not name.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function mapToPaths(array $input): array
    {
        // Keyed on the wire name, so the only spelling accepted here is the
        // one the rules above validate. A payload using dots would otherwise
        // be stored without ever having been checked.
        $known = [];

        foreach (array_keys(PageSchema::fields($this->pageKey())) as $path) {
            $known[PageSchema::inputName($path)] = $path;
        }

        $mapped = [];

        foreach ($input as $name => $value) {
            if (isset($known[$name])) {
                $mapped[$known[$name]] = $value;
            }
        }

        return $mapped;
    }
}
