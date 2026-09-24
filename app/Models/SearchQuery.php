<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One search term, with a count of how often it has been run.
 *
 * Written only by App\Services\Catalogue\SearchService. See the migration for
 * why this is a counter per term rather than a log of events, and for what is
 * deliberately not stored alongside it.
 */
class SearchQuery extends Model
{
    use HasFactory;

    protected $table = 'tbl_search_queries';

    protected $fillable = [
        'term',
        'search_count',
        'result_count',
        'last_searched_at',
    ];

    protected function casts(): array
    {
        return [
            'search_count' => 'integer',
            'result_count' => 'integer',
            'last_searched_at' => 'datetime',
        ];
    }

    /**
     * The stored spelling of a term.
     *
     * Lower-cased and collapsed to single spaces, so the same search typed
     * three ways is one row. Trimmed to the column width rather than rejected:
     * a 300-character paste is still a search somebody ran.
     */
    public static function normalise(?string $term): string
    {
        $term = preg_replace('/\s+/u', ' ', trim((string) $term)) ?? '';

        return Str::lower(Str::limit($term, 120, ''));
    }

    /**
     * Terms that found nothing - the useful half of this table.
     */
    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->where('result_count', 0);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('search_count')->orderByDesc('last_searched_at');
    }
}
