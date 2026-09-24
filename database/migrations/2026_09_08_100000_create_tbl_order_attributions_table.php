<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where an order came from.
     *
     * A table of its own rather than fifteen more columns on tbl_orders, for
     * the reason tbl_product_details and tbl_order_payments already exist: the
     * orders row is read on every list, filter and count in the panel, and
     * attribution is read on exactly two screens. Keeping it here leaves the
     * hot row narrow.
     *
     * Every value is a snapshot taken when the order was placed. A visitor who
     * clears their cookies afterwards changes nothing here, which is the whole
     * point of copying it onto the order rather than looking it up later.
     *
     * Orders placed before this table existed simply have no row. The panel
     * says so, and the report leaves them out rather than inventing a source
     * for them.
     */
    public function up(): void
    {
        Schema::create('tbl_order_attributions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('tbl_orders')
                ->cascadeOnDelete();

            /* ---------------------------------------------------- first touch */

            // How the visitor found the shop the very first time. Written once
            // and never overwritten - that is what makes it worth having.
            $table->string('first_source', 100)->nullable();
            $table->string('first_medium', 100)->nullable();
            $table->string('first_campaign', 100)->nullable();
            $table->string('first_term', 100)->nullable();
            $table->string('first_content', 100)->nullable();
            $table->string('first_referrer', 500)->nullable();
            $table->string('first_landing_page', 255)->nullable();
            $table->timestamp('first_at')->nullable();

            /* ----------------------------------------------------- last touch */

            // The most recent identifiable source before the order. Updated
            // whenever a visitor arrives from somewhere new; untouched by
            // moving around inside the site.
            $table->string('last_source', 100)->nullable();
            $table->string('last_medium', 100)->nullable();
            $table->string('last_campaign', 100)->nullable();
            $table->string('last_term', 100)->nullable();
            $table->string('last_content', 100)->nullable();
            $table->string('last_referrer', 500)->nullable();
            $table->string('last_landing_page', 255)->nullable();
            $table->timestamp('last_at')->nullable();

            // The anonymous id the cookie carried. Not a person and not linked
            // to one - there are no accounts on this shop - but it is what
            // ties two orders from the same browser together if that is ever
            // worth asking about.
            $table->string('visitor_token', 40)->nullable();

            $table->timestamps();

            // One attribution per order.
            $table->unique('order_id');

            // The report groups by these pairs and by campaign.
            $table->index(['last_source', 'last_medium'], 'order_attr_last_index');
            $table->index(['first_source', 'first_medium'], 'order_attr_first_index');
            $table->index('last_campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_order_attributions');
    }
};
