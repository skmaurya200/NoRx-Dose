<?php

namespace App\Services\Chat;

use App\Models\ChatEmbedding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps tbl_chat_embeddings in step with the store.
 *
 * Runs from `php artisan chat:index`, and again whenever a product, category
 * or post is saved. Records whose text has not changed keep the vector they
 * already have - the checksum exists so that re-indexing a catalogue costs one
 * embedding call for the thing that actually changed rather than one per
 * record per run.
 */
class ChatIndexService
{
    public const CACHE_KEY = 'chat:embeddings:index';

    public function __construct(
        private readonly ChatCorpusService $corpus,
        private readonly GeminiService $gemini,
    ) {}

    /**
     * Rebuilds the whole index.
     *
     * @param  bool  $force  re-embed even records whose text is unchanged
     * @param  (callable(int, int): void)|null  $progress
     * @return array{indexed: int, skipped: int, removed: int}
     */
    public function rebuild(bool $force = false, ?callable $progress = null): array
    {
        $records = $this->corpus->all();

        $existing = ChatEmbedding::query()
            ->get(['id', 'source_type', 'source_key', 'checksum', 'dimensions', 'model'])
            ->keyBy(fn (ChatEmbedding $row) => $row->source_type.':'.$row->source_key);

        $model = (string) config('chat.retrieval.embedding_model');
        $dimensions = (int) config('chat.retrieval.dimensions');

        $stale = [];
        $seen = [];
        $skipped = 0;

        foreach ($records as $record) {
            $key = $record['source_type'].':'.$record['source_key'];
            $seen[] = $key;
            $record['checksum'] = hash('sha256', $record['body']);

            $current = $existing->get($key);

            // A changed embedding model or dimensionality invalidates every
            // stored vector: they are no longer comparable to a new query.
            $unchanged = $current !== null
                && $current->checksum === $record['checksum']
                && $current->model === $model
                && $current->dimensions === $dimensions;

            if ($unchanged && ! $force) {
                $skipped++;

                continue;
            }

            $stale[] = $record;
        }

        $indexed = 0;
        $batchSize = max(1, (int) config('chat.retrieval.batch_size'));

        foreach (array_chunk($stale, $batchSize) as $batch) {
            $vectors = $this->gemini->embed(
                array_map(fn (array $record) => $this->embeddable($record), $batch),
                GeminiService::TASK_DOCUMENT,
            );

            DB::transaction(function () use ($batch, $vectors, $model, &$indexed) {
                foreach ($batch as $offset => $record) {
                    ChatEmbedding::query()->updateOrCreate(
                        ['source_type' => $record['source_type'], 'source_key' => $record['source_key']],
                        [
                            'source_id' => $record['source_id'],
                            'title' => $record['title'],
                            'body' => $record['body'],
                            'url' => $record['url'],
                            'checksum' => $record['checksum'],
                            'model' => $model,
                            'dimensions' => count($vectors[$offset]),
                            'vector' => $vectors[$offset],
                        ],
                    );

                    $indexed++;
                }
            });

            if ($progress !== null) {
                $progress($indexed, count($stale));
            }
        }

        // Anything the corpus no longer produces has been unpublished or
        // deleted, and must stop being retrievable immediately.
        $removed = $this->prune($seen);

        $this->forget();

        return ['indexed' => $indexed, 'skipped' => $skipped, 'removed' => $removed];
    }

    /**
     * Drops the index rows for records that no longer exist.
     *
     * @param  list<string>  $seen  "type:key" of everything the corpus produced
     */
    private function prune(array $seen): int
    {
        $keep = collect($seen)->mapWithKeys(fn (string $key) => [$key => true]);

        $removed = 0;

        ChatEmbedding::query()
            ->select(['id', 'source_type', 'source_key'])
            ->chunkById(200, function ($rows) use ($keep, &$removed) {
                $ids = $rows
                    ->reject(fn (ChatEmbedding $row) => $keep->has($row->source_type.':'.$row->source_key))
                    ->modelKeys();

                if ($ids !== []) {
                    $removed += ChatEmbedding::query()->whereKey($ids)->delete();
                }
            });

        return $removed;
    }

    /**
     * The title is repeated into the embedded text on purpose: a product's
     * name is the strongest signal it has, and burying it in one line of a
     * long description dilutes it away.
     *
     * @param  array<string, mixed>  $record
     */
    private function embeddable(array $record): string
    {
        return trim($record['title']."\n".$record['body']);
    }

    /**
     * The scored index is cached per request-burst; a write has to invalidate
     * it or a re-index would not be visible until it expired.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.':corpus');
    }
}
