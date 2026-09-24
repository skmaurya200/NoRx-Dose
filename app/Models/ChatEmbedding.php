<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One indexed piece of the store, with the vector that was computed for it.
 *
 * Rows are written only by App\Services\Chat\ChatIndexService. Nothing here is
 * ever shown to a visitor directly: the body is what grounds an answer, and
 * the title and url are what a citation is built from.
 */
class ChatEmbedding extends Model
{
    public const TYPES = ['product', 'category', 'faq', 'page', 'post', 'guide'];

    protected $table = 'tbl_chat_embeddings';

    protected $fillable = [
        'source_type',
        'source_key',
        'source_id',
        'title',
        'body',
        'url',
        'checksum',
        'model',
        'dimensions',
        'vector',
    ];

    protected function casts(): array
    {
        return [
            'vector' => 'array',
            'dimensions' => 'integer',
            'source_id' => 'integer',
        ];
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('source_type', $type);
    }
}
