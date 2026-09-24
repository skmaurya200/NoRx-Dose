<?php

namespace App\Models;

use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A journal category - the chips above the post list.
 */
class BlogCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tbl_blog_categories';

    /**
     * slug is excluded: it is derived from the name by SlugGenerator, never
     * accepted from a form, so a client cannot claim someone else's URL.
     */
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }

    /**
     * Only the posts a visitor can actually reach - what the chip counts.
     */
    public function publishedPosts(): HasMany
    {
        return $this->posts()->published();
    }

    /* --------------------------------------------------------------- scopes */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = SqlLike::contains($term);

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)->orWhere('slug', 'like', $like);
        });
    }
}
