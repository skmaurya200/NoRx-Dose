<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tbl_product_categories';

    /**
     * Slug is absent on purpose: it is derived from the name by the service so
     * a client cannot post a slug that collides with an existing URL.
     */
    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'accordions',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'accordions' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function imageUrl(): ?string
    {
        // Served straight out of public/ - there is no storage disk in play.
        return $this->image_path ? asset($this->image_path) : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Panel default: manual sort_order first, then alphabetical, so a curated
     * order survives and everything else stays predictable.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
