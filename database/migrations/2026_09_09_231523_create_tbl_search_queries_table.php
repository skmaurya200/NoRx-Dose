<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What people type into the search box.
 *
 * One row per distinct term rather than one per search. A shop this size would
 * accumulate millions of event rows to answer the only two questions anyone
 * actually asks of them - what do people look for, and what do they look for
 * and not find - and both are answered by a counter.
 *
 * Nothing here identifies anybody. No IP, no session, no timestamp per event:
 * a search term is the one part of a search that is useful to keep, and the
 * rest would be personal data collected for no stated purpose.
 *
 * The counters also feed the storefront: the suggestion panel offers popular
 * terms before anything has been typed, which is what makes the box useful on
 * first click.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_search_queries', function (Blueprint $table) {
            $table->id();

            // Lower-cased and collapsed to single spaces before it is written,
            // so "Magnesium  " and "magnesium" are one row and not two.
            $table->string('term', 120)->unique();

            $table->unsignedInteger('search_count')->default(1);

            // How many products the term matched the last time it was run.
            // Zero is the interesting number: it is a product people want and
            // the shop does not list, or one whose name does not match what
            // they call it.
            $table->unsignedInteger('result_count')->default(0);

            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            // The two orderings the panel and the suggestion panel use.
            $table->index(['search_count', 'term']);
            $table->index(['result_count', 'search_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_search_queries');
    }
};
