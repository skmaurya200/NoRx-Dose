<?php

namespace Tests\Feature\Storefront;

use App\Models\ChatEmbedding;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Chat\ChatRetrievalService;
use App\Services\Chat\ChatToolService;
use App\Services\Content\PageContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shop's own records as the assistant's knowledge, kept current.
 *
 * The model is never taught anything about this shop; it is handed what the
 * shop says at the moment it asks. So the property worth guarding is that an
 * edit made a second ago is what the next answer is built from - not the
 * wording the indexer happened to embed an hour earlier.
 */
class ChatKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
    }

    /**
     * Indexes a record as it stands, the way the scheduled command would.
     */
    private function embed(string $type, string $key, Product|ProductCategory|null $of = null): void
    {
        ChatEmbedding::query()->create([
            'source_type' => $type,
            'source_key' => $key,
            'source_id' => $of?->id,
            'title' => 'whatever was true when this was embedded',
            'body' => 'whatever was true when this was embedded',
            'url' => null,
            'checksum' => str_repeat('a', 64),
            'model' => 'text-embedding-test',
            'dimensions' => 3,
            'vector' => [1.0, 0.0, 0.0],
        ]);
    }

    private function retrieval(): ChatRetrievalService
    {
        return app(ChatRetrievalService::class);
    }

    /* ------------------------------------------------------- price changes */

    /**
     * The headline case: a price change needs no indexing at all, because no
     * price was ever indexed.
     */
    public function test_a_price_change_is_answered_with_the_new_price(): void
    {
        $product = Product::factory()->create(['name' => 'Aurum Sleep Complex', 'price' => 499]);

        $tools = app(ChatToolService::class);

        $this->assertSame(499.0, $tools->getProductDetails('Aurum Sleep Complex')['product']['price']);

        $product->update(['price' => 599]);

        $this->assertSame(599.0, $tools->getProductDetails('Aurum Sleep Complex')['product']['price']);
        $this->assertSame(599.0, $tools->searchProducts('Aurum Sleep Complex')['products'][0]['price']);
    }

    /* ------------------------------------------------------- text changes */

    /**
     * The vector is the only thing taken from the index. The words an answer
     * is built from come from the shop as it is now, so a rewritten
     * description is quoted immediately rather than at the next indexing run.
     */
    public function test_an_edited_description_is_used_before_the_index_catches_up(): void
    {
        $product = Product::factory()->create([
            'name' => 'Aurum Sleep Complex',
            'short_description' => 'A gentle evening blend.',
        ]);

        $this->embed('product', (string) $product->id, $product);

        $product->update(['short_description' => 'Now formulated with valerian root.']);

        $match = collect($this->retrieval()->search('Aurum Sleep Complex'))
            ->firstWhere('ref', 'product:'.$product->id);

        $this->assertNotNull($match);
        $this->assertStringContainsString('valerian root', $match['body']);
        $this->assertStringNotContainsString('whatever was true when this was embedded', $match['body']);
    }

    /**
     * A page an operator has rewritten is the page the assistant answers from.
     */
    public function test_edited_page_content_is_what_the_assistant_reads(): void
    {
        app(PageContentService::class)->save('faq', [], [], [], ['questions.items' => [
            [
                'topic' => 'Delivery',
                'question' => 'Do you deliver on Sundays?',
                'answer' => 'We now deliver on Sundays in selected cities.',
            ],
        ]]);

        $information = app(ChatToolService::class)->getWebsiteInformation('Do you deliver on Sundays?');

        $this->assertTrue($information['found']);
        $this->assertSame('Do you deliver on Sundays?', $information['information'][0]['title']);
        $this->assertStringContainsString('selected cities', $information['information'][0]['body']);
    }

    /* ------------------------------------------------------- new records */

    /**
     * A product added after the last indexing run has no vector, so meaning
     * cannot reach it. Its words still must.
     */
    public function test_a_product_added_since_the_last_indexing_run_is_still_found(): void
    {
        $indexed = Product::factory()->create(['name' => 'Older Blend']);
        $this->embed('product', (string) $indexed->id, $indexed);

        $fresh = Product::factory()->create(['name' => 'Brand New Nootropic']);

        $refs = array_column($this->retrieval()->search('Brand New Nootropic'), 'ref');

        $this->assertContains('product:'.$fresh->id, $refs);
    }

    /**
     * And the reverse: something withdrawn stops being knowledge at once,
     * rather than lingering until the index is pruned.
     */
    public function test_an_unpublished_product_stops_being_reachable_immediately(): void
    {
        $product = Product::factory()->create(['name' => 'Discontinued Blend']);
        $this->embed('product', (string) $product->id, $product);

        $product->update(['status' => 'draft', 'published_at' => null]);

        $refs = array_column($this->retrieval()->search('Discontinued Blend'), 'ref');

        $this->assertNotContains('product:'.$product->id, $refs);
        $this->assertSame(0, app(ChatToolService::class)->searchProducts('Discontinued Blend')['found']);
    }

    /* ------------------------------------------------ the cache in between */

    /**
     * The corpus is cached because building it walks the catalogue. Saving a
     * record has to drop that cache, or the assistant knows an older shop than
     * the one on screen for as long as the cache lives.
     */
    public function test_saving_a_record_drops_the_cached_knowledge(): void
    {
        Product::factory()->create(['name' => 'First Blend']);

        // Warms the cache.
        $this->retrieval()->search('First Blend');

        $added = Product::factory()->create(['name' => 'Second Blend']);

        $refs = array_column($this->retrieval()->search('Second Blend'), 'ref');

        $this->assertContains('product:'.$added->id, $refs);
    }

    public function test_a_renamed_category_is_known_by_its_new_name(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Sleep Aids']);
        Product::factory()->for($category, 'category')->create();

        $this->retrieval()->search('Sleep Aids');

        $category->update(['name' => 'Rest And Recovery']);

        $names = array_column(app(ChatToolService::class)->searchCategories('Rest')['categories'], 'name');

        $this->assertSame(['Rest And Recovery'], $names);
    }
}
