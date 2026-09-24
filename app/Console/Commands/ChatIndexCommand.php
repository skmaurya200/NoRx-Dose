<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatIndexService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Builds the chat's retrieval index.
 *
 * Safe to run repeatedly and on a schedule: only records whose text changed
 * since the last run are re-embedded, so the routine cost is a handful of
 * calls rather than one per product.
 */
class ChatIndexCommand extends Command
{
    protected $signature = 'chat:index {--force : Re-embed every record, even unchanged ones}';

    protected $description = 'Index products, categories, FAQ, pages and journal posts for the chat assistant';

    public function handle(ChatIndexService $index): int
    {
        $force = (bool) $this->option('force');

        $this->info($force ? 'Re-embedding every record…' : 'Indexing changed records…');

        try {
            $result = $index->rebuild($force, function (int $done, int $total) {
                $this->line("  embedded {$done}/{$total}");
            });
        } catch (Throwable $exception) {
            // The message, not the trace: an API key can end up in a trace.
            $this->error('Indexing failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Embedded', 'Unchanged', 'Removed'],
            [[$result['indexed'], $result['skipped'], $result['removed']]],
        );

        return self::SUCCESS;
    }
}
