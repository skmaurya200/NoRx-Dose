<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Keeps the chat's retrieval index in step with the catalogue.
|
| Hourly rather than on save: embedding is an outbound call, and paying for one
| in the middle of an operator's "Save product" would make the panel feel slow
| for a freshness nobody notices. Only records whose text actually changed are
| re-embedded, so a quiet hour costs nothing.
*/
Schedule::command('chat:index')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
