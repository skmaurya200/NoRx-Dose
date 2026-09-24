<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SearchQuery;
use App\Services\Chat\ChatToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The controlled functions the assistant reaches this shop's data through.
 *
 * The model never touches a table: it names one of these and Laravel decides
 * what that means. So what matters here is the shape of what comes back - that
 * it is small, that it is current, and that it carries nothing an answer has no
 * business quoting.
 */
class ChatToolTest extends TestCase
{
    use RefreshDatabase;

    private function tools(): ChatToolService
    {
        return app(ChatToolService::class);
    }

    /* ------------------------------------------------------------ products */

    public function test_a_product_search_returns_at_most_one_page(): void
    {
        Product::factory()->count(9)->create(['name' => 'Sleep Support']);

        $result = $this->tools()->searchProducts('Sleep Support');

        $this->assertSame(9, $result['found']);
        $this->assertCount(ChatToolService::MAX_RECORDS, $result['products']);
        $this->assertTrue($result['has_more']);
    }

    /**
     * The whole reason the model is not given a database: it cannot be handed
     * what the shop would not print on the page itself.
     */
    public function test_a_product_never_carries_cost_price_or_stock_counts(): void
    {
        Product::factory()->create(['name' => 'Magnesium', 'cost_price' => 4.10, 'stock_quantity' => 73]);

        $product = $this->tools()->searchProducts('Magnesium')['products'][0];

        $this->assertArrayNotHasKey('cost_price', $product);
        $this->assertArrayNotHasKey('stock_quantity', $product);
        $this->assertArrayNotHasKey('sku', $product);
        // Availability is the answerable half of a stock count.
        $this->assertTrue($product['available']);
    }

    public function test_a_price_filter_is_not_widened_by_the_search_index(): void
    {
        Product::factory()->create(['name' => 'Cheap Magnesium', 'price' => 10]);
        Product::factory()->create(['name' => 'Costly Magnesium', 'price' => 90]);

        $result = $this->tools()->searchProducts('Magnesium', ['max_price' => 20]);

        $this->assertSame(1, $result['found']);
        $this->assertSame('Cheap Magnesium', $result['products'][0]['name']);
    }

    public function test_an_unpublished_product_is_not_reachable(): void
    {
        Product::factory()->draft()->create(['name' => 'Secret Formula']);

        $this->assertSame(0, $this->tools()->searchProducts('Secret Formula')['found']);
        $this->assertFalse($this->tools()->getProductDetails('Secret Formula')['found']);
    }

    /**
     * "Zolpidem 10mg" has to find the 10mg, not whichever Zolpidem scored best.
     */
    public function test_product_details_prefer_the_exact_name(): void
    {
        Product::factory()->create(['name' => 'Zolpidem 5mg', 'price' => 30]);
        Product::factory()->create(['name' => 'Zolpidem 10mg', 'price' => 50]);

        $result = $this->tools()->getProductDetails('Zolpidem 10mg');

        $this->assertTrue($result['found']);
        $this->assertSame('Zolpidem 10mg', $result['product']['name']);
        $this->assertSame(50.0, $result['product']['price']);
    }

    public function test_a_product_that_does_not_exist_says_so_rather_than_guessing(): void
    {
        $result = $this->tools()->getProductDetails('Unobtainium 400mg');

        $this->assertFalse($result['found']);
        $this->assertNull($result['product']);
        $this->assertSame('Unobtainium 400mg', $result['searched_for']);
    }

    public function test_categories_come_back_with_their_published_counts(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Sleep Aids']);
        Product::factory()->count(2)->for($category, 'category')->create();
        Product::factory()->draft()->for($category, 'category')->create();

        $result = $this->tools()->searchCategories('Sleep');

        $this->assertSame(1, $result['found']);
        $this->assertSame('Sleep Aids', $result['categories'][0]['name']);
        $this->assertSame(2, $result['categories'][0]['products']);
    }

    /* ------------------------------------------------------------- website */

    public function test_store_information_is_read_from_the_checkouts_own_configuration(): void
    {
        config(['shop.shipping' => [['id' => 'usps', 'name' => 'U.S.P.S (2-3 Days):', 'note' => 'Delivered in 3-5 business days', 'cost' => 35]]]);

        $result = $this->tools()->call('getStoreInformation', []);

        $this->assertTrue($result['found']);
        $this->assertSame(['card'], $result['payment']['methods']);
        $this->assertFalse($result['payment']['cash_on_delivery']);
        $this->assertSame([['name' => 'U.S.P.S (2-3 Days)', 'cost' => '$35.00', 'delivery_time' => 'Delivered in 3-5 business days']], $result['delivery']['methods']);
        $this->assertFalse($result['orders_after_placing']['tracking_number']);
    }

    public function test_a_search_term_is_data_never_a_query(): void
    {
        Product::factory()->create(['name' => 'Magnesium']);

        $result = $this->tools()->searchProducts("%' OR 1=1 --");

        $this->assertSame(0, $result['found']);
        $this->assertDatabaseCount('tbl_products', 1);
    }

    public function test_product_details_carry_the_live_price(): void
    {
        Product::factory()->create(['name' => 'Aurum Sleep Complex', 'price' => 218]);

        $result = $this->tools()->getProductDetails('Aurum Sleep Complex');

        $this->assertSame('$218.00', $result['product']['price_formatted']);
    }

    /* ----------------------------------------------------------- analytics */

    /**
     * A shop that has never taken an order is not asked what is trending: the
     * function is not offered, so there is nothing to answer emptily.
     */
    public function test_analytics_functions_are_withheld_until_there_is_data(): void
    {
        $available = array_keys($this->tools()->available());

        $this->assertContains('searchProducts', $available);
        $this->assertNotContains('getTrendingProducts', $available);
        $this->assertNotContains('getPopularSearches', $available);
    }

    public function test_popular_searches_appear_once_visitors_have_searched(): void
    {
        SearchQuery::factory()->create(['term' => 'zolpidem', 'search_count' => 40]);
        SearchQuery::factory()->create(['term' => 'melatonin', 'search_count' => 5]);

        $this->assertContains('getPopularSearches', array_keys($this->tools()->available()));

        $result = $this->tools()->getPopularSearches();

        $this->assertTrue($result['found']);
        $this->assertSame('zolpidem', $result['searches'][0]['term']);
        $this->assertSame(40, $result['searches'][0]['times_searched']);
    }

    /* ------------------------------------------------------------- calling */

    public function test_a_function_the_shop_does_not_offer_cannot_be_called(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->tools()->call('getTrendingProducts', []);
    }

    public function test_an_invented_function_name_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->tools()->call('runRawSql', ['sql' => 'select * from tbl_admins']);
    }

    /**
     * Arguments are read, not trusted: a filter the declarations never
     * described is dropped rather than reaching a query.
     */
    public function test_arguments_outside_the_declared_shape_are_ignored(): void
    {
        Product::factory()->create(['name' => 'Magnesium', 'price' => 40]);

        $result = $this->tools()->call('searchProducts', [
            'query' => 'Magnesium',
            'status' => 'draft',
            'order_by' => 'cost_price',
            'limit' => 500,
        ]);

        $this->assertSame(1, $result['found']);
        $this->assertCount(1, $result['products']);
    }

    public function test_the_declarations_describe_only_the_offered_functions(): void
    {
        $declared = array_column($this->tools()->declarations(), 'name');

        $this->assertSame(array_keys($this->tools()->available()), $declared);

        foreach ($this->tools()->declarations() as $declaration) {
            $this->assertNotSame('', $declaration['description']);
            $this->assertSame('object', $declaration['parameters']['type']);
        }
    }
}
