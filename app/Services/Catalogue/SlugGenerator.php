<?php

namespace App\Services\Catalogue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slugs are derived here rather than accepted from the request, so a client
 * cannot claim a URL that belongs to another record - or post a slug that
 * happens to collide with a storefront route.
 */
final class SlugGenerator
{
    /**
     * @param  class-string<Model>  $model
     * @param  int|null  $ignoreId  the record being updated, so it does not
     *                              collide with its own existing slug
     */
    public static function for(string $model, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);

        // Str::slug() returns "" for input that is entirely non-latin or
        // punctuation, and an empty slug would break every URL built from it.
        if ($base === '') {
            $base = 'item';
        }

        $base = Str::limit($base, 150, '');
        $slug = $base;
        $suffix = 1;

        while (self::taken($model, $slug, $ignoreId)) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * Soft-deleted rows are included: their slug still occupies the unique
     * index, so ignoring them would hand back a value that cannot be inserted.
     */
    private static function taken(string $model, string $slug, ?int $ignoreId): bool
    {
        /** @var Builder $query */
        $query = $model::query()->where('slug', $slug);

        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
