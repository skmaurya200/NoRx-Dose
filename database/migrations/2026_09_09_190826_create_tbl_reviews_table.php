<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer reviews.
     *
     * Nothing written here is public until an operator says so. A review
     * arrives as "pending" and the storefront only ever queries the approved
     * ones, so the shop cannot be made to publish whatever a stranger types
     * into a form.
     *
     * A review may name a product or stand on its own - the reviews page
     * carries general ones about the shop, and a product page carries the ones
     * about it. One table for both, because they are the same thing said about
     * different subjects.
     */
    public function up(): void
    {
        Schema::create('tbl_reviews', function (Blueprint $table) {
            $table->id();

            // Nulled rather than cascaded: a product removed from the
            // catalogue should not silently delete what people said about it.
            $table->foreignId('product_id')->nullable()
                ->constrained('tbl_products')
                ->nullOnDelete();

            // The order this review is tied to, when it is tied to one. That
            // link is what "verified purchase" means on this shop.
            $table->foreignId('order_id')->nullable()
                ->constrained('tbl_orders')
                ->nullOnDelete();

            $table->string('author_name', 120);

            // Never shown publicly. Kept so an operator can reach the reviewer
            // about their review, and to spot one person filing ten of them.
            $table->string('author_email', 180)->nullable();

            // "Denver" in "Verified customer · Denver". Free text, optional.
            $table->string('location', 80)->nullable();

            // Matches the filter chips on the reviews page.
            $table->enum('category', ['quality', 'delivery', 'packaging', 'support', 'value'])
                ->default('quality');

            $table->unsignedTinyInteger('rating');
            $table->string('title', 150)->nullable();
            $table->text('body');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // The one review pulled out at the top of the reviews page.
            $table->boolean('is_featured')->default(false);

            // Set when the review is tied to a real order. Stored rather than
            // derived so it stays true after that order is deleted.
            $table->boolean('is_verified')->default(false);

            $table->unsignedInteger('helpful_count')->default(0);

            // Who wrote it. A shop seeding its own testimonials is a normal
            // thing to do; pretending they came from customers is not, so the
            // panel records which is which.
            $table->enum('source', ['customer', 'manager'])->default('customer');

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('approved_by')->nullable()
                ->constrained('tbl_admins')
                ->nullOnDelete();

            $table->ipAddress('ip_address')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The storefront asks for "approved, newest first", often for one
            // product; the panel asks for "pending".
            $table->index(['status', 'created_at']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_reviews');
    }
};
