<?php

namespace App\Http\Requests\APIs\Setting;

use App\Support\Settings\SettingSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The Settings form, validated against the schema rather than against a fixed
 * list of rules.
 *
 * The form is generated, so its rules are too: a setting the schema does not
 * name is never read, which is what stops a crafted payload writing a value
 * the site has no slot for.
 */
class UpdateSettingsRequest extends FormRequest
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
        $bucket = config('admin.uploads.settings');

        $rules = [
            'fields' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['nullable', 'string'],
        ];

        foreach (SettingSchema::fields() as $path => $field) {
            $type = $field['type'] ?? 'text';

            // The wire name, not the schema path: a dot would be read as
            // Laravel's array separator and the rule would never match.
            $name = SettingSchema::inputName($path);

            if ($type === 'image') {
                $rules['images.'.$name] = [
                    'nullable', 'image',
                    'mimes:'.implode(',', $bucket['mimes']),
                    'max:'.$bucket['max_kb'],
                ];

                continue;
            }

            $rules['fields.'.$name] = match ($path) {
                'contact.email' => ['nullable', 'string', 'email:rfc', 'max:180'],
                // Handed to visitors by the chat assistant, so it has to be a
                // number somebody can actually dial.
                'contact.whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s().\-]{7,25}$/'],
                default => match ($type) {
                    'textarea' => ['nullable', 'string', 'max:2000'],
                    'url' => ['nullable', 'string', 'url', 'max:255'],
                    default => ['nullable', 'string', 'max:255'],
                },
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = [];

        foreach (SettingSchema::fields() as $path => $field) {
            $names['fields.'.SettingSchema::inputName($path)] = mb_strtolower($field['label']);
            $names['images.'.SettingSchema::inputName($path)] = mb_strtolower($field['label']);
        }

        return $names;
    }

    /**
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
     * @return array<int, string>
     */
    public function removeImages(): array
    {
        $paths = array_keys(SettingSchema::fields());

        return array_values(array_intersect((array) $this->input('remove_images', []), $paths));
    }

    /**
     * Turns "brand__name" keys back into "brand.name" and drops anything the
     * schema does not name.
     *
     * Keyed on the wire name, so the only spelling accepted here is the one
     * the rules above validate - a payload using dots would otherwise be
     * stored without ever having been checked.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function mapToPaths(array $input): array
    {
        $known = [];

        foreach (array_keys(SettingSchema::fields()) as $path) {
            $known[SettingSchema::inputName($path)] = $path;
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
