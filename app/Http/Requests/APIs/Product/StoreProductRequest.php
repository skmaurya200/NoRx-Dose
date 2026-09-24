<?php

namespace App\Http\Requests\APIs\Product;

use App\Models\Product;
use App\Support\PublicUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
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
        return array_merge(
            $this->productRules(),
            $this->detailRules(),
            $this->packRules(),
            $this->imageRules(),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function productRules(): array
    {
        return [
            'category_id' => [
                'required',
                Rule::exists('tbl_product_categories', 'id')->whereNull('deleted_at'),
            ],

            'name' => ['required', 'string', 'min:2', 'max:180'],

            // Optional: the service mints one when it is left blank. Uniqueness
            // spans trashed rows too, because the unique index does.
            'sku' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', $this->uniqueSku()],

            'brand' => ['nullable', 'string', 'max:120'],
            'short_description' => ['nullable', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:50000'],

            // Bounded above as well as below: the column is decimal(10,2), and
            // a larger number would be truncated by the database rather than
            // rejected here.
            // No longer required from the form: the panel took the price
            // fields out, and a product is priced by its first pack size. Still
            // accepted, because an API client may price a product directly and
            // the rules are shared between both callers.
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'gt:price'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],

            'unit' => ['nullable', 'string', 'max:40'],
            'weight_grams' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],

            'track_inventory' => ['sometimes', 'boolean'],
            // Only demanded when inventory is actually tracked, so an untracked
            // service product does not need a made-up number.
            'stock_quantity' => ['required_if:track_inventory,true', 'nullable', 'integer', 'min:0', 'max:4294967295'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'allow_backorder' => ['sometimes', 'boolean'],

            'status' => ['required', Rule::in(Product::STATUSES)],
            'is_featured' => ['sometimes', 'boolean'],

            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function detailRules(): array
    {
        return [
            'ingredients' => ['nullable', 'string', 'max:5000'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'how_to_use' => ['nullable', 'string', 'max:5000'],
            'storage' => ['nullable', 'string', 'max:2000'],
            'warnings' => ['nullable', 'string', 'max:5000'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:180'],
            'shelf_life_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'is_vegetarian' => ['sometimes', 'boolean'],
            'is_gluten_free' => ['sometimes', 'boolean'],

            // Repeater rows. Bounded so a scripted post cannot store an
            // unbounded JSON blob in the column.
            'specifications' => ['nullable', 'array', 'max:30'],
            'specifications.*.label' => ['nullable', 'string', 'max:80'],
            'specifications.*.value' => ['nullable', 'string', 'max:255'],

            // Detail sections an operator adds for this product alone. Capped
            // low on purpose: past a handful it is a page, not a product.
            // Same flag as packs_present, for the same reason: a repeater whose
            // every row has been deleted posts no key at all, and without this
            // "delete the last section" would silently keep it.
            'extra_sections_present' => ['sometimes', 'boolean'],
            'extra_sections' => ['nullable', 'array', 'max:12'],
            'extra_sections.*.label' => ['nullable', 'string', 'max:80'],
            'extra_sections.*.body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Pack sizes - the "Select size" chooser. Optional: a product with none is
     * sold as a single item.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function packRules(): array
    {
        return [
            'packs' => ['nullable', 'array', 'max:12'],

            // A row counts as filled in once it has either a label or a price,
            // so a half-typed row is caught instead of silently dropped.
            'packs.*.label' => ['nullable', 'required_with:packs.*.price', 'string', 'max:60'],
            'packs.*.price' => ['nullable', 'required_with:packs.*.label', 'numeric', 'min:0', 'max:99999999.99'],
            'packs.*.compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'packs.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'packs.*.is_best_value' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function imageRules(): array
    {
        $max = (int) config('admin.catalogue.max_gallery_images');

        return [
            'thumbnail' => PublicUpload::rules('products'),
            'gallery' => ['nullable', 'array', 'max:'.$max],
            'gallery.*' => PublicUpload::rules('products', required: true),
        ];
    }

    /**
     * Cross-field rules that need more than one value at once.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A margin check the operator will thank us for: selling below cost
            // is almost always a typo, and it is cheaper to catch here than in
            // a month of reports.
            $price = $this->input('price');
            $cost = $this->input('cost_price');

            if (is_numeric($price) && is_numeric($cost) && (float) $cost > (float) $price) {
                $validator->errors()->add(
                    'cost_price',
                    'The cost price is higher than the selling price. Check the figures.',
                );
            }

            $this->validatePacks($validator);
            $this->validatePriceExists($validator);
        });
    }

    /**
     * A product has to cost something.
     *
     * The panel no longer asks for a price directly - the first pack size sets
     * it - so this is where "you gave me neither" is caught. Without it a
     * product would save at zero and go on sale for nothing.
     */
    private function validatePriceExists(Validator $validator): void
    {
        if (is_numeric($this->input('price'))) {
            return;
        }

        foreach ((array) $this->input('packs') as $row) {
            if (is_array($row) && is_numeric($row['price'] ?? null)) {
                return;
            }
        }

        $validator->errors()->add(
            'packs',
            'Add at least one pack size with a price - the first one sets what this product costs.',
        );
    }

    /**
     * Rules that need to see the pack rows together rather than one at a time.
     */
    private function validatePacks(Validator $validator): void
    {
        $rows = $this->input('packs');

        if (! is_array($rows)) {
            return;
        }

        $seen = [];
        $bestValue = 0;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $price = $row['price'] ?? null;
            $compare = $row['compare_at_price'] ?? null;

            if ($label === '' && ! is_numeric($price)) {
                continue; // an untouched blank row
            }

            // (product_id, label) is unique in the database; catching it here
            // gives the operator a field error instead of a 500.
            $key = mb_strtolower($label);

            if ($key !== '' && isset($seen[$key])) {
                $validator->errors()->add(
                    "packs.{$index}.label",
                    'Two sizes cannot share the same label.',
                );
            }

            $seen[$key] = true;

            if (is_numeric($compare) && is_numeric($price) && (float) $compare <= (float) $price) {
                $validator->errors()->add(
                    "packs.{$index}.compare_at_price",
                    'The compare-at price must be higher than this size\'s price.',
                );
            }

            if (! empty($row['is_best_value'])) {
                $bestValue++;
            }
        }

        if ($bestValue > 1) {
            $validator->errors()->add(
                'packs',
                'Only one size can be marked as the best value.',
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'compare_at_price' => 'compare-at price',
            'stock_quantity' => 'stock quantity',
            'low_stock_threshold' => 'low stock threshold',
            'how_to_use' => 'how to use',

            // Without these, a repeater failure reads
            // "The specifications.0.label field must not be greater than...".
            'specifications.*.label' => 'specification label',
            'specifications.*.value' => 'specification value',
            'extra_sections.*.label' => 'section heading',
            'extra_sections.*.body' => 'section text',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Choose a category for this product.',
            'category_id.exists' => 'That category no longer exists.',
            'name.required' => 'Give the product a name.',
            'sku.regex' => 'The SKU can use letters, numbers, dots, dashes and underscores only.',
            'sku.unique' => 'That SKU is already in use by another product.',
            'price.required' => 'Enter a selling price.',
            'compare_at_price.gt' => 'The compare-at price must be higher than the selling price.',
            'stock_quantity.required_if' => 'Enter the stock quantity, or turn inventory tracking off.',
            'status.required' => 'Choose a status.',
            'gallery.max' => 'You can upload up to :max gallery images.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalised = [
            'name' => $this->trimmed('name'),
            'currency' => strtoupper((string) ($this->input('currency') ?: config('shop.currency', 'USD'))),

            // Unchecked checkboxes are simply absent from a multipart post, so
            // each one is resolved to a real boolean before the rules run. These
            // are merged unconditionally on purpose: absent genuinely means
            // false for a checkbox.
            'track_inventory' => $this->boolean('track_inventory'),
            'allow_backorder' => $this->boolean('allow_backorder'),
            'is_featured' => $this->boolean('is_featured'),
            'is_vegetarian' => $this->boolean('is_vegetarian'),
            'is_gluten_free' => $this->boolean('is_gluten_free'),
        ];

        if ($this->has('sku')) {
            $normalised['sku'] = $this->trimmed('sku') === ''
                ? null
                : strtoupper((string) $this->trimmed('sku'));
        }

        if ($this->has('brand')) {
            $normalised['brand'] = $this->trimmed('brand');
        }

        // "" from an empty number or date input is not numeric, so it becomes
        // null - but ONLY when the field was actually submitted. Merging these
        // unconditionally would let an update that never mentioned
        // published_at silently unpublish the product; absent means "leave it
        // alone", empty means "clear it".
        foreach (['compare_at_price', 'cost_price', 'weight_grams', 'shelf_life_months', 'published_at'] as $key) {
            if ($this->has($key)) {
                $normalised[$key] = $this->nullIfBlank($key);
            }
        }

        $this->merge($normalised);
    }

    /**
     * Everything the service needs, already validated. Files are excluded -
     * they are pulled from the request separately and never mass-assigned.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        // packs live in their own table, so they must never be mass-assigned
        // onto the product row.
        $data = $this->safe()->except([
            'thumbnail', 'gallery', 'remove_thumbnail', 'packs', 'extra_sections_present',
        ]);

        // The flag says the form manages the sections, so an emptied repeater
        // really does clear them rather than reading as "leave them alone".
        if (! $this->has('extra_sections') && $this->boolean('extra_sections_present')) {
            $data['extra_sections'] = [];
        }

        // Defaults for the columns a partial form may leave out entirely.
        $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);
        $data['low_stock_threshold'] = (int) ($data['low_stock_threshold'] ?? 5);
        $data['track_inventory'] = (bool) ($data['track_inventory'] ?? false);
        $data['allow_backorder'] = (bool) ($data['allow_backorder'] ?? false);
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);
        $data['is_vegetarian'] = (bool) ($data['is_vegetarian'] ?? false);
        $data['is_gluten_free'] = (bool) ($data['is_gluten_free'] ?? false);

        return $data;
    }

    /**
     * The pack rows, blank ones dropped and ordered as they were entered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function packs(): array
    {
        $rows = $this->safe()->input('packs');

        if (! is_array($rows)) {
            return [];
        }

        $packs = [];
        $position = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));

            // A row the operator added and never filled in is not an error, it
            // is just nothing - the same rule the specifications repeater uses.
            if ($label === '' || ! is_numeric($row['price'] ?? null)) {
                continue;
            }

            $packs[] = [
                'label' => $label,
                'price' => (float) $row['price'],
                'compare_at_price' => is_numeric($row['compare_at_price'] ?? null)
                    ? (float) $row['compare_at_price']
                    : null,
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'is_best_value' => (bool) ($row['is_best_value'] ?? false),
                'sort_order' => ++$position,
            ];
        }

        return $packs;
    }

    /**
     * Whether the request mentioned packs at all. An absent key means "leave
     * the existing sizes alone"; an empty array means "remove them".
     *
     * The panel form also sends a packs_present flag, because a form whose
     * every size row has been deleted posts no packs key at all - without the
     * flag, clearing the last size in the browser would silently keep it.
     */
    public function touchesPacks(): bool
    {
        return $this->has('packs') || $this->boolean('packs_present');
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function galleryFiles(): array
    {
        return array_values(array_filter((array) $this->file('gallery')));
    }

    protected function uniqueSku(): Unique
    {
        return Rule::unique('tbl_products', 'sku');
    }

    private function trimmed(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : ($value === null ? null : (string) $value);
    }

    private function nullIfBlank(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }
}
