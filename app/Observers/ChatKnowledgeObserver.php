<?php

namespace App\Observers;

use App\Services\Chat\ChatIndexService;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the assistant's knowledge in step with the shop as it is edited.
 *
 * The corpus is cached for a quarter of an hour because building it walks the
 * whole catalogue, which is fine until an operator changes a price, renames a
 * product or rewrites an FAQ and then asks the assistant about it. Waiting out
 * a cache is the assistant appearing to know an older shop than the one on
 * screen, and that is exactly what a customer would notice.
 *
 * What this does not do is embed. Indexing is an outbound call to a paid API,
 * and a save is a request somebody is waiting on: re-embedding here would put
 * a network round trip inside the save button. Dropping the cache is enough
 * for every answer to read the new words immediately - the vector catches up
 * on the next scheduled run, and until it does, the word search reaches what
 * has changed.
 */
class ChatKnowledgeObserver
{
    public function __construct(private readonly ChatIndexService $index) {}

    public function saved(Model $model): void
    {
        $this->index->forget();
    }

    public function deleted(Model $model): void
    {
        $this->index->forget();
    }

    public function restored(Model $model): void
    {
        $this->index->forget();
    }
}
