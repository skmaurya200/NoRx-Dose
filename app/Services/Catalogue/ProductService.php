<?php

namespace App\Services\Catalogue;

use App\Exceptions\Catalogue\LimitExceededException;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPack;
use App\Support\HtmlSanitizer;
use App\Support\PublicUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The catalogue's write path. A product is really three tables - the row, its
 * long-form detail and its gallery - so every mutation runs inside a
 * transaction: a half-written product with no detail row would render a broken
 * product page on the storefront.
 */
class ProductService
{
    private const UPLOAD_BUCKET = 'products';

    /**
     * Columns the list screen is allowed to sort by. An allow-list, because
     * feeding a request value straight into orderBy() is SQL injection.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'price', 'stock_quantity', 'created_at', 'status'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        [$column, $direction] = $this->sort($filters);

        return Product::query()
            ->with('category:id,name')
            ->search($filters['search'] ?? null)
            ->when(($filters['category_id'] ?? '') !== '', function (Builder $query) use ($filters) {
                $query->where('category_id', (int) $filters['category_id']);
            })
            ->when(($filters['status'] ?? '') !== '', function (Builder $query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when(($filters['stock'] ?? '') !== '', function (Builder $query) use ($filters) {
                $this->applyStockFilter($query, (string) $filters['stock']);
            })
            ->orderBy($column, $direction)
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $gallery
     * @param  array<int, array<string, mixed>>|null  $packs
     */
    public function create(
        array $data,
        ?UploadedFile $thumbnail = null,
        array $gallery = [],
        ?array $packs = null,
    ): Product {
        $detail = $this->pullDetail($data);
        $data = $this->resolvePrice($this->cleanDescription($data), $packs, creating: true);

        return DB::transaction(function () use ($data, $detail, $thumbnail, $gallery, $packs) {
            $product = new Product($data);
            $product->slug = SlugGenerator::for(Product::class, $data['name']);
            $product->sku = $this->resolveSku($data['sku'] ?? null, $data['name']);

            // A product that goes straight to active gets its publish stamp
            // now, so the storefront's published_at filter lets it through.
            if ($product->status === 'active' && $product->published_at === null) {
                $product->published_at = now();
            }

            if ($thumbnail !== null) {
                $product->thumbnail_path = PublicUpload::store($thumbnail, self::UPLOAD_BUCKET);
            }

            $product->save();

            $product->detail()->create($detail);

            if ($packs !== null) {
                $this->syncPacks($product, $packs);
            }

            if ($gallery !== []) {
                $this->addImages($product, $gallery);
            }

            return $product->load(['category:id,name', 'detail', 'images', 'packs']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $gallery
     */
    public function update(
        Product $product,
        array $data,
        ?UploadedFile $thumbnail = null,
        array $gallery = [],
        bool $removeThumbnail = false,
        ?array $packs = null,
    ): Product {
        $detail = $this->pullDetail($data);
        $data = $this->resolvePrice($this->cleanDescription($data), $packs, creating: false);

        return DB::transaction(function () use ($product, $data, $detail, $thumbnail, $gallery, $removeThumbnail, $packs) {
            $product->fill($data);

            if ($product->isDirty('name')) {
                $product->slug = SlugGenerator::for(Product::class, $product->name, $product->id);
            }

            if ($product->isDirty('status') && $product->status === 'active' && $product->published_at === null) {
                $product->published_at = now();
            }

            if ($thumbnail !== null) {
                $product->thumbnail_path = PublicUpload::replace($thumbnail, self::UPLOAD_BUCKET, $product->thumbnail_path);
            } elseif ($removeThumbnail && $product->thumbnail_path) {
                PublicUpload::delete($product->thumbnail_path, self::UPLOAD_BUCKET);
                $product->thumbnail_path = null;
            }

            $product->save();

            // updateOrCreate rather than update: a product created before the
            // detail table existed would otherwise have nothing to write to.
            $product->detail()->updateOrCreate(['product_id' => $product->id], $detail);

            // null means the request never mentioned packs, so the existing
            // sizes are left alone; an empty array means remove them.
            if ($packs !== null) {
                $this->syncPacks($product, $packs);
            }

            if ($gallery !== []) {
                $this->addImages($product, $gallery);
            }

            return $product->load(['category:id,name', 'detail', 'images', 'packs']);
        });
    }

    /* --------------------------------------------------------------- packs */

    /**
     * Replace a product's pack sizes with the rows given.
     *
     * Matched on label rather than wiped and recreated, so a row keeps its id
     * across an edit - an order line will point at a pack, and re-creating the
     * row on every save would orphan it.
     *
     * @param  array<int, array<string, mixed>>  $packs
     */
    public function syncPacks(Product $product, array $packs): void
    {
        DB::transaction(function () use ($product, $packs) {
            $existing = $product->packs()->get()->keyBy(fn (ProductPack $pack) => mb_strtolower($pack->label));
            $keep = [];

            foreach ($packs as $row) {
                $key = mb_strtolower($row['label']);
                $pack = $existing->get($key);

                if ($pack !== null) {
                    $pack->update($row);
                } else {
                    $pack = $product->packs()->create($row);
                }

                $keep[] = $pack->id;
            }

            // Anything the operator removed from the form.
            $product->packs()->whereNotIn('id', $keep ?: [0])->delete();

            $this->syncPriceFromPacks($product);
        });
    }

    /**
     * The default (first) pack drives the product's headline price.
     *
     * Without this the card in a listing and the buy box on the product page
     * could quote different figures for the same product, which is the kind of
     * mismatch a customer notices at checkout.
     */
    private function syncPriceFromPacks(Product $product): void
    {
        $first = $product->packs()->first();

        if ($first === null) {
            return;
        }

        $product->forceFill([
            'price' => $first->price,
            'compare_at_price' => $first->compare_at_price,
        ])->save();
    }

    /**
     * Soft delete, so an order that references this product still resolves its
     * name. Files and gallery rows are left intact for the same reason.
     */
    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function updateStatus(Product $product, string $status): Product
    {
        $product->status = $status;

        if ($status === 'active' && $product->published_at === null) {
            $product->published_at = now();
        }

        $product->save();

        return $product;
    }

    /* ------------------------------------------------------------- gallery */

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, ProductImage>
     */
    public function addImages(Product $product, array $files): array
    {
        $limit = (int) config('admin.catalogue.max_gallery_images');
        $existing = $product->images()->count();

        if ($existing + count($files) > $limit) {
            throw new LimitExceededException(
                "A product can have at most {$limit} gallery images. This one already has {$existing}.",
            );
        }

        $position = (int) $product->images()->max('sort_order');
        $created = [];

        foreach ($files as $file) {
            $image = new ProductImage([
                'sort_order' => ++$position,
                // The first image ever added doubles as the primary one, so a
                // gallery is never left without a lead image.
                'is_primary' => $existing === 0 && $created === [],
            ]);

            $image->image_path = PublicUpload::store($file, self::UPLOAD_BUCKET);
            $product->images()->save($image);

            $created[] = $image;
        }

        return $created;
    }

    public function deleteImage(Product $product, ProductImage $image): void
    {
        // Belt and braces: the route already scopes the binding, but a service
        // must not assume its caller did.
        abort_unless($image->product_id === $product->id, 404);

        DB::transaction(function () use ($product, $image) {
            $wasPrimary = $image->is_primary;

            PublicUpload::delete($image->image_path, self::UPLOAD_BUCKET);
            $image->delete();

            // Never leave the gallery without a primary.
            if ($wasPrimary) {
                $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
            }
        });
    }

    public function setPrimaryImage(Product $product, ProductImage $image): void
    {
        abort_unless($image->product_id === $product->id, 404);

        DB::transaction(function () use ($product, $image) {
            $product->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);

            // The card thumbnail follows the primary image, so the listing and
            // the product page cannot disagree about which photo leads.
            $product->forceFill(['thumbnail_path' => $image->image_path])->save();
        });
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorderImages(Product $product, array $orderedIds): void
    {
        DB::transaction(function () use ($product, $orderedIds) {
            foreach (array_values($orderedIds) as $position => $id) {
                // Scoped to this product, so a stray id from another product
                // cannot be reordered through here.
                $product->images()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    /* ------------------------------------------------------------- helpers */

    /**
     * Split the flat form payload into the product half and the detail half.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function pullDetail(array &$data): array
    {
        $keys = [
            'ingredients', 'benefits', 'how_to_use', 'storage', 'warnings',
            'country_of_origin', 'manufacturer', 'shelf_life_months',
            'is_vegetarian', 'is_gluten_free', 'specifications', 'extra_sections',
        ];

        $detail = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $detail[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        // Blank repeater rows arrive whenever someone adds a row and does not
        // fill it in; storing them would render empty lines on the page. A row
        // needs both halves - a heading with nothing under it is not a section.
        if (array_key_exists('specifications', $detail)) {
            $detail['specifications'] = $this->rows($detail['specifications'], 'label', 'value');
        }

        if (array_key_exists('extra_sections', $detail)) {
            $detail['extra_sections'] = $this->sections($detail['extra_sections']);
        }

        return $detail;
    }

    /**
     * An operator's own detail sections.
     *
     * Separate from rows() because the body is rich text: a heading with only
     * an empty paragraph under it is a blank row even though the string is not
     * empty, and the markup has to be filtered against the same allow-list as
     * everything else the storefront prints unescaped.
     *
     * @return list<array{label: string, body: string}>|null
     */
    private function sections(mixed $rows): ?array
    {
        if (! is_array($rows)) {
            return null;
        }

        $kept = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $body = $row['body'] ?? null;

            if ($label === '' || HtmlSanitizer::isBlank($body)) {
                continue;
            }

            $kept[] = ['label' => $label, 'body' => HtmlSanitizer::clean($body)];
        }

        return $kept === [] ? null : $kept;
    }

    /**
     * Something for the price column before the packs are written.
     *
     * The panel no longer asks for a price - syncPriceFromPacks() sets it from
     * the first size once the sizes exist - but that runs after the row is
     * inserted, and tbl_products.price is NOT NULL. So a create borrows the
     * first pack's figure to get the insert through, and an update that was
     * sent no usable price simply keeps the one it already has.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $packs
     * @return array<string, mixed>
     */
    private function resolvePrice(array $data, ?array $packs, bool $creating): array
    {
        if (is_numeric($data['price'] ?? null)) {
            return $data;
        }

        unset($data['price']);

        if (! $creating) {
            return $data;
        }

        foreach ($packs ?? [] as $pack) {
            if (is_array($pack) && is_numeric($pack['price'] ?? null)) {
                $data['price'] = (float) $pack['price'];

                return $data;
            }
        }

        // Nothing to go on. The request refuses this case, so reaching here
        // means an API client bypassed the form rules; zero is visible and
        // wrong rather than a crash.
        $data['price'] = 0.0;

        return $data;
    }

    /**
     * The full description is rich text from the panel's editor, so it is
     * filtered against the same allow-list as a post body before it is stored
     * - the storefront prints it unescaped.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cleanDescription(array $data): array
    {
        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::isBlank($data['description'])
                ? null
                : HtmlSanitizer::clean($data['description']);
        }

        return $data;
    }

    /**
     * One repeater's rows, with the half-filled ones dropped.
     *
     * @return list<array<string, string>>|null
     */
    private function rows(mixed $rows, string $first, string $second): ?array
    {
        if (! is_array($rows)) {
            return null;
        }

        $kept = array_values(array_filter(
            $rows,
            fn ($row) => is_array($row)
                && trim((string) ($row[$first] ?? '')) !== ''
                && trim((string) ($row[$second] ?? '')) !== '',
        ));

        return $kept === [] ? null : $kept;
    }

    /**
     * Uses the SKU the operator typed, or derives a readable one. The unique
     * index is still the final authority - this only avoids the obvious clash.
     */
    private function resolveSku(?string $sku, string $name): string
    {
        $sku = strtoupper(trim((string) $sku));

        if ($sku !== '') {
            return $sku;
        }

        $prefix = Str::upper(Str::limit(Str::slug($name, ''), 6, ''));
        $prefix = $prefix !== '' ? $prefix : 'AW';

        do {
            $candidate = $prefix.'-'.Str::upper(Str::random(6));
        } while (Product::withTrashed()->where('sku', $candidate)->exists());

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    private function sort(array $filters): array
    {
        $column = (string) ($filters['sort'] ?? 'created_at');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));

        return [
            in_array($column, self::SORTABLE, true) ? $column : 'created_at',
            $direction === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function applyStockFilter(Builder $query, string $stock): void
    {
        match ($stock) {
            'out' => $query->where('track_inventory', true)
                ->where('allow_backorder', false)
                ->where('stock_quantity', '<=', 0),

            // Compares two columns, so each product is judged against its own
            // threshold rather than one global number.
            'low' => $query->where('track_inventory', true)
                ->where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),

            'in' => $query->where(function (Builder $q) {
                $q->where('track_inventory', false)
                    ->orWhere('allow_backorder', true)
                    ->orWhere('stock_quantity', '>', 0);
            }),

            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $requested = (int) ($filters['per_page'] ?? config('admin.catalogue.per_page'));

        return min(max($requested, 1), (int) config('admin.catalogue.max_per_page'));
    }
}
