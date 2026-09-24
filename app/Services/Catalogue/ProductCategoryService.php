<?php

namespace App\Services\Catalogue;

use App\Exceptions\Catalogue\ResourceInUseException;
use App\Models\ProductCategory;
use App\Support\HtmlSanitizer;
use App\Support\PublicUpload;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Every write to the category tree goes through here. Controllers stay thin and
 * the rules - slug derivation, image lifecycle, delete safety - live in one
 * place instead of being restated per endpoint.
 */
class ProductCategoryService
{
    private const UPLOAD_BUCKET = 'categories';

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return ProductCategory::query()
            ->with('parent:id,name')
            // A count, not the rows: the list only shows a number, and loading
            // every product per category would be a needless N+1.
            ->withCount('products')
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters) {
                $like = SqlLike::contains((string) $filters['search']);
                $query->where(fn (Builder $q) => $q->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like));
            })
            ->when(($filters['status'] ?? '') !== '', function (Builder $query) use ($filters) {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->when(($filters['parent_id'] ?? '') !== '', function (Builder $query) use ($filters) {
                $filters['parent_id'] === 'root'
                    ? $query->whereNull('parent_id')
                    : $query->where('parent_id', (int) $filters['parent_id']);
            })
            ->ordered()
            ->paginate(self::perPage($filters))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image = null): ProductCategory
    {
        return DB::transaction(function () use ($data, $image) {
            $requested = self::requestedSlug($data);

            $category = new ProductCategory(self::prepare($data));

            // An operator's own slug still goes through the generator, so it is
            // slugified and de-duplicated like any other - the override chooses
            // the words, not the uniqueness rules.
            $category->slug = SlugGenerator::for(ProductCategory::class, $requested ?? $data['name']);

            if ($image !== null) {
                $category->image_path = PublicUpload::store($image, self::UPLOAD_BUCKET);
            }

            $category->save();

            return $category;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        ProductCategory $category,
        array $data,
        ?UploadedFile $image = null,
        bool $removeImage = false,
    ): ProductCategory {
        $this->guardAgainstCycle($category, $data['parent_id'] ?? null);

        return DB::transaction(function () use ($category, $data, $image, $removeImage) {
            $requested = self::requestedSlug($data);

            $category->fill(self::prepare($data));

            if ($requested !== null) {
                // Typed deliberately, so it wins - including over a name that
                // changed in the same save.
                $category->slug = SlugGenerator::for(ProductCategory::class, $requested, $category->id);
            } elseif ($category->isDirty('name')) {
                // Only re-slug when the name actually moved, so an unrelated
                // edit does not silently invalidate an indexed storefront URL.
                $category->slug = SlugGenerator::for(ProductCategory::class, $category->name, $category->id);
            }

            if ($image !== null) {
                $category->image_path = PublicUpload::replace($image, self::UPLOAD_BUCKET, $category->image_path);
            } elseif ($removeImage && $category->image_path) {
                PublicUpload::delete($category->image_path, self::UPLOAD_BUCKET);
                $category->image_path = null;
            }

            $category->save();

            return $category;
        });
    }

    /**
     * Soft delete. The image is deliberately left on disk so a restore is not
     * left pointing at a file that no longer exists.
     */
    public function delete(ProductCategory $category): void
    {
        if ($category->products()->exists()) {
            throw new ResourceInUseException(
                'This category still has products in it. Move or remove them first.',
            );
        }

        if ($category->children()->exists()) {
            throw new ResourceInUseException(
                'This category has sub-categories. Remove those first.',
            );
        }

        $category->delete();
    }

    public function toggleActive(ProductCategory $category): ProductCategory
    {
        $category->is_active = ! $category->is_active;
        $category->save();

        return $category;
    }

    /**
     * The slug typed in the panel, or null when the field was left blank and
     * the name should decide.
     *
     * @param  array<string, mixed>  $data
     */
    private static function requestedSlug(array $data): ?string
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        return $slug === '' ? null : $slug;
    }

    /**
     * What the model is allowed to receive. The slug is removed because it is
     * derived above rather than mass-assigned, and the description is filtered
     * against the same allow-list as a post body: it is written in the panel's
     * editor and printed unescaped on the storefront.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function prepare(array $data): array
    {
        unset($data['slug']);

        if (array_key_exists('description', $data)) {
            $data['description'] = HtmlSanitizer::isBlank($data['description'])
                ? null
                : HtmlSanitizer::clean($data['description']);
        }

        if (array_key_exists('accordions', $data)) {
            $data['accordions'] = self::accordions($data['accordions']);
        }

        return $data;
    }

    /**
     * The category's expandable sections.
     *
     * A heading with only an empty paragraph under it is a blank row even
     * though the string is not empty, so the body is judged by the sanitiser
     * rather than by trim(). Both halves are required: a heading with nothing
     * under it renders as an accordion that opens onto nothing.
     *
     * @return list<array{label: string, body: string}>|null
     */
    private static function accordions(mixed $rows): ?array
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

            $kept[] = [
                'label' => $label,
                // Blank is allowed: the storefront falls back to a plain mark,
                // so a section without one still matches the rest.
                'icon' => mb_substr(trim((string) ($row['icon'] ?? '')), 0, 8),
                'body' => HtmlSanitizer::clean($body),
            ];
        }

        return $kept === [] ? null : $kept;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function perPage(array $filters): int
    {
        $requested = (int) ($filters['per_page'] ?? config('admin.catalogue.per_page'));

        // Clamped, so ?per_page=100000 cannot be used to pull the whole table
        // (or exhaust memory) in one request.
        return min(max($requested, 1), (int) config('admin.catalogue.max_per_page'));
    }

    /**
     * A category cannot be its own parent, nor sit under one of its own
     * descendants - either creates a loop that makes the tree infinite to walk
     * and would hang every breadcrumb render.
     */
    private function guardAgainstCycle(ProductCategory $category, mixed $parentId): void
    {
        if ($parentId === null || $parentId === '') {
            return;
        }

        $parentId = (int) $parentId;

        if ($parentId === $category->id) {
            throw new ResourceInUseException('A category cannot be its own parent.');
        }

        // Walk up from the proposed parent. The depth cap is a backstop in case
        // a loop already exists in the data.
        $cursor = ProductCategory::find($parentId);
        $depth = 0;

        while ($cursor !== null && $depth++ < 50) {
            if ((int) $cursor->parent_id === $category->id) {
                throw new ResourceInUseException(
                    'That parent sits underneath this category, which would create a loop.',
                );
            }

            $cursor = $cursor->parent_id ? ProductCategory::find($cursor->parent_id) : null;
        }
    }
}
