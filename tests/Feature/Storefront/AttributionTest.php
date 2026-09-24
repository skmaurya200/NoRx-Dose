<?php

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Models\OrderAttribution;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Attribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Where a guest came from, and how it reaches their order.
 *
 * The shop has no customer accounts, so every one of these walks the same path
 * a real shopper would: land on a page, browse, then place an order, carrying
 * nothing but the attribution cookie between them.
 */
class AttributionTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'price' => 50.00,
            'stock_quantity' => 100,
        ]);
    }

    private function cookieName(): string
    {
        return config('shop.attribution.cookie');
    }

    /**
     * A page view, with an optional referring site.
     *
     * withCookies takes plain values and encrypts them the way a browser's
     * stored cookie would arrive, so the middleware reads them exactly as it
     * would in production.
     *
     * @param  array<string, string>  $cookies
     */
    private function visit(string $url, ?string $referrer = null, array $cookies = [])
    {
        $headers = $referrer ? ['referer' => $referrer] : [];

        return $this->withCookies($cookies)->get($url, $headers);
    }

    /**
     * The attribution cookie as it stands after a response, decrypted, ready
     * to hand to the next request - which is what a browser does.
     *
     * @return array<string, string>
     */
    private function carry($response): array
    {
        $cookie = $response->getCookie($this->cookieName());

        return $cookie ? [$this->cookieName() => $cookie->getValue()] : [];
    }

    /**
     * Places an order the way the checkout does, carrying whatever the visitor
     * picked up on the way.
     *
     * withCredentials is what makes the cookie travel: the test client leaves
     * cookies off a JSON request unless asked, mirroring fetch(), and the real
     * checkout asks for them with credentials: 'same-origin'.
     *
     * @param  array<string, string>  $cookies
     */
    private function order(array $cookies = []): Order
    {
        $this->withCredentials()
            ->withCookies($cookies)
            ->postJson('/api/storefront/checkout', [
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                'shipping_method' => 'usps',
                'first_name' => 'Ada', 'last_name' => 'Lovelace',
                'email' => 'ada@example.com', 'phone' => '4155550132',
                'street' => '2500 Mission Street', 'city' => 'San Francisco',
                'state' => 'CA', 'postal_code' => '94110', 'country' => 'US',
                'card_holder' => 'Ada Lovelace',
                'card_number' => '4242424242424242',
                'card_expiry' => '12 / '.str_pad((string) ((now()->year + 2) % 100), 2, '0', STR_PAD_LEFT),
                'card_cvc' => '123',
            ])
            ->assertStatus(201);

        return Order::latest('id')->with('attribution')->firstOrFail();
    }

    /**
     * Land somewhere, then buy - the whole journey in one call.
     *
     * @return OrderAttribution
     */
    private function journey(string $url, ?string $referrer = null)
    {
        $landing = $this->visit($url, $referrer);

        return $this->order($this->carry($landing))->attribution;
    }

    /* ================================================== the twelve journeys */

    /** 1. A guest types the address in and buys. */
    public function test_a_direct_visit_is_recorded_as_direct(): void
    {
        $attribution = $this->journey('/');

        $this->assertSame('direct', $attribution->first_source);
        $this->assertSame('direct', $attribution->first_medium);
        $this->assertNull($attribution->first_referrer);
        $this->assertSame('/', $attribution->first_landing_page);
    }

    /** 2. A guest arrives from a Google search. */
    public function test_google_is_recorded_as_organic(): void
    {
        $attribution = $this->journey('/product/'.$this->product->slug, 'https://www.google.com/');

        $this->assertSame('google', $attribution->last_source);
        $this->assertSame('organic', $attribution->last_medium);
        $this->assertSame('https://www.google.com/', $attribution->last_referrer);
        $this->assertSame('/product/'.$this->product->slug, $attribution->last_landing_page);
    }

    /** 3. Bing counts the same way, and so do the rest. */
    public function test_every_search_engine_is_organic(): void
    {
        foreach ([
            'https://www.bing.com/search?q=magnesium' => 'bing',
            'https://search.yahoo.com/' => 'yahoo',
            'https://duckduckgo.com/' => 'duckduckgo',
            'https://yandex.ru/search/' => 'yandex',
            // A regional domain is the same search engine.
            'https://www.google.co.uk/' => 'google',
        ] as $referrer => $expected) {
            $attribution = $this->journey('/', $referrer);

            $this->assertSame($expected, $attribution->last_source, $referrer);
            $this->assertSame('organic', $attribution->last_medium, $referrer);
        }
    }

    /** 4 & 5. Social networks are named, not treated as plain referrals. */
    public function test_social_networks_are_recorded_as_social(): void
    {
        foreach ([
            'https://www.facebook.com/' => 'facebook',
            'https://l.instagram.com/' => 'instagram',
            'https://www.youtube.com/watch?v=abc' => 'youtube',
            'https://www.linkedin.com/feed/' => 'linkedin',
            'https://t.co/abc123' => 'twitter',
        ] as $referrer => $expected) {
            $attribution = $this->journey('/', $referrer);

            $this->assertSame($expected, $attribution->last_source, $referrer);
            $this->assertSame('social', $attribution->last_medium, $referrer);
        }
    }

    /** 6. A backlink from someone else's article - the off-page SEO case. */
    public function test_an_external_backlink_is_recorded_as_a_referral(): void
    {
        $attribution = $this->journey(
            '/product/'.$this->product->slug,
            'https://example-blog.com/article/best-supplements',
        );

        $this->assertSame('example-blog.com', $attribution->last_source);
        $this->assertSame('referral', $attribution->last_medium);
        $this->assertSame('https://example-blog.com/article/best-supplements', $attribution->last_referrer);
    }

    /** 7. A tagged link. */
    public function test_utm_parameters_are_stored(): void
    {
        $attribution = $this->journey(
            '/product/'.$this->product->slug
                .'?utm_source=facebook&utm_medium=social&utm_campaign=summer_sale&utm_content=ad1&utm_term=magnesium',
        );

        $this->assertSame('facebook', $attribution->last_source);
        $this->assertSame('social', $attribution->last_medium);
        $this->assertSame('summer_sale', $attribution->last_campaign);
        $this->assertSame('ad1', $attribution->last_content);
        $this->assertSame('magnesium', $attribution->last_term);
    }

    /**
     * A tagged backlink: the tags win over what the referrer would have said,
     * which is the point of tagging one.
     */
    public function test_utm_beats_the_referrer(): void
    {
        $attribution = $this->journey(
            '/product/abc?utm_source=exampleblog&utm_medium=backlink&utm_campaign=offpage_seo',
            'https://example-blog.com/article',
        );

        $this->assertSame('exampleblog', $attribution->last_source);
        $this->assertSame('backlink', $attribution->last_medium);
        $this->assertSame('offpage_seo', $attribution->last_campaign);
        // The referring page is still kept - it is how the backlink is found.
        $this->assertSame('https://example-blog.com/article', $attribution->last_referrer);
    }

    /** 8. Tagged arrival, then a browse around the shop. */
    public function test_a_campaign_survives_browsing(): void
    {
        $landing = $this->visit('/?utm_source=instagram&utm_medium=social&utm_campaign=spring');
        $cookies = $this->carry($landing);

        // Three internal pages, each linking from the last.
        foreach (['/shop', '/product/'.$this->product->slug, '/cart'] as $page) {
            $next = $this->visit($page, 'http://localhost'.($page === '/shop' ? '/' : '/shop'), $cookies);
            $cookies = $this->carry($next) ?: $cookies;
        }

        $attribution = $this->order($cookies)->attribution;

        $this->assertSame('instagram', $attribution->last_source);
        $this->assertSame('spring', $attribution->last_campaign);

        // The landing page is where they came in, not the last page they saw.
        // Compared loosely because the query string is normalised - the
        // parameters are all there, in whatever order the request canonicalised.
        $this->assertStringStartsWith('/?', $attribution->last_landing_page);
        $this->assertStringContainsString('utm_source=instagram', $attribution->last_landing_page);
        $this->assertStringContainsString('utm_campaign=spring', $attribution->last_landing_page);
    }

    /** 9. Found on Google, came back through Facebook, bought. */
    public function test_first_touch_is_kept_when_a_later_source_arrives(): void
    {
        $first = $this->visit('/', 'https://www.google.com/');
        $second = $this->visit('/shop', 'https://www.facebook.com/', $this->carry($first));

        $attribution = $this->order($this->carry($second))->attribution;

        $this->assertSame('google', $attribution->first_source);
        $this->assertSame('organic', $attribution->first_medium);

        $this->assertSame('facebook', $attribution->last_source);
        $this->assertSame('social', $attribution->last_medium);
    }

    /**
     * 10. The rule the whole design turns on: moving around the shop is not a
     * new source, so the referrer never becomes our own domain.
     */
    public function test_internal_navigation_never_changes_the_source(): void
    {
        $landing = $this->visit('/', 'https://www.google.com/');
        $cookies = $this->carry($landing);

        foreach (['/shop', '/all-products', '/cart', '/checkout'] as $page) {
            $next = $this->visit($page, 'http://localhost/shop', $cookies);
            $cookies = $this->carry($next) ?: $cookies;
        }

        $attribution = $this->order($cookies)->attribution;

        $this->assertSame('google', $attribution->last_source);
        $this->assertSame('organic', $attribution->last_medium);
        $this->assertSame('https://www.google.com/', $attribution->last_referrer);
        $this->assertStringNotContainsString('localhost', (string) $attribution->last_referrer);
    }

    /** 11. Add to cart, leave, come back later on the same cookie. */
    public function test_attribution_survives_a_return_visit(): void
    {
        $landing = $this->visit('/product/'.$this->product->slug, 'https://example-blog.com/review');
        $cookies = $this->carry($landing);

        // Back the next day, straight to the cart with no referrer at all.
        $return = $this->visit('/cart', null, $cookies);
        $cookies = $this->carry($return) ?: $cookies;

        $attribution = $this->order($cookies)->attribution;

        $this->assertSame('example-blog.com', $attribution->first_source);
        $this->assertSame('example-blog.com', $attribution->last_source);
    }

    /** 12. A product opened with no referrer at all. */
    public function test_a_bare_product_visit_is_direct(): void
    {
        $attribution = $this->journey('/product/'.$this->product->slug);

        $this->assertSame('direct', $attribution->first_source);
        $this->assertSame('/product/'.$this->product->slug, $attribution->first_landing_page);
    }

    /* ==================================================== the awkward cases */

    /**
     * 13. Two tabs. The cookie is shared, so the second arrival is the last
     * touch and the first is still the first.
     */
    public function test_a_second_tab_updates_the_last_touch_only(): void
    {
        $tabOne = $this->visit('/', 'https://www.google.com/');
        $tabTwo = $this->visit('/shop?utm_source=newsletter&utm_medium=email', null, $this->carry($tabOne));

        $attribution = $this->order($this->carry($tabTwo))->attribution;

        $this->assertSame('google', $attribution->first_source);
        $this->assertSame('newsletter', $attribution->last_source);
        $this->assertSame('email', $attribution->last_medium);
    }

    /**
     * 14. A checkout that fails validation and is then corrected. The failed
     * POST must not disturb what the page view established.
     */
    public function test_a_failed_checkout_does_not_disturb_attribution(): void
    {
        $landing = $this->visit('/', 'https://www.facebook.com/');
        $cookies = $this->carry($landing);

        // Missing everything: a 422 straight back.
        $this->withCredentials()
            ->withCookies($cookies)
            ->postJson('/api/storefront/checkout', [])
            ->assertStatus(422);

        $attribution = $this->order($cookies)->attribution;

        $this->assertSame('facebook', $attribution->first_source);
        $this->assertSame('facebook', $attribution->last_source);
    }

    /**
     * 15/16. A second order from the same browser gets its own attribution
     * row, carrying the same history.
     */
    public function test_a_returning_customer_gets_their_own_attribution_row(): void
    {
        $landing = $this->visit('/', 'https://www.google.com/');
        $cookies = $this->carry($landing);

        $first = $this->order($cookies);
        $second = $this->order($cookies);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('google', $first->attribution->last_source);
        $this->assertSame('google', $second->attribution->last_source);
        $this->assertSame(2, OrderAttribution::count());
    }

    /* ============================================================== hygiene */

    /**
     * A visitor who blocks cookies still gets an order - recorded as direct,
     * which is what the shop actually knows about them.
     */
    public function test_an_order_without_a_cookie_is_recorded_as_direct(): void
    {
        $attribution = $this->order()->attribution;

        $this->assertNotNull($attribution);
        $this->assertSame('direct', $attribution->first_source);
    }

    /**
     * The panel is never asked to render a hostile value, because one cannot
     * be stored in the first place.
     */
    public function test_malformed_utm_values_are_cleaned(): void
    {
        $attribution = $this->journey(
            '/?utm_source='.urlencode('<script>alert(1)</script>')
                .'&utm_campaign='.urlencode('summer "sale" & more'),
        );

        $this->assertStringNotContainsString('<', (string) $attribution->last_source);
        $this->assertStringNotContainsString('"', (string) $attribution->last_campaign);
        $this->assertSame('scriptalert1script', $attribution->last_source);
    }

    public function test_oversized_values_are_capped(): void
    {
        $attribution = $this->journey(
            '/?utm_campaign='.str_repeat('a', 400),
            'https://example.com/'.str_repeat('b', 900),
        );

        $this->assertLessThanOrEqual(100, mb_strlen((string) $attribution->last_campaign));
        $this->assertLessThanOrEqual(500, mb_strlen((string) $attribution->last_referrer));
    }

    /**
     * A referrer that is not a web address is a broken or hostile header, not
     * a link anybody followed.
     */
    public function test_a_non_http_referrer_is_ignored(): void
    {
        $attribution = $this->journey('/', 'javascript:alert(1)');

        $this->assertSame('direct', $attribution->last_source);
        $this->assertNull($attribution->last_referrer);
    }

    /** A lookalike domain is not the real one. */
    public function test_a_lookalike_domain_is_not_matched_as_a_search_engine(): void
    {
        $attribution = $this->journey('/', 'https://notgoogle.com/');

        $this->assertSame('notgoogle.com', $attribution->last_source);
        $this->assertSame('referral', $attribution->last_medium);
    }

    /** The panel is not a visit by a shopper. */
    public function test_the_admin_panel_is_not_tracked(): void
    {
        $response = $this->get('/manager/login');

        $this->assertNull($response->getCookie($this->cookieName(), false));
    }

    /* ================================================================ unit */

    public function test_an_internal_referrer_yields_no_touch(): void
    {
        $request = Request::create('http://localhost/cart', 'GET');
        $request->headers->set('referer', 'http://localhost/shop');

        $this->assertNull(Attribution::detect($request));
    }

    public function test_the_landing_page_keeps_its_query_but_not_the_host(): void
    {
        $request = Request::create('http://localhost/product/abc?utm_source=x', 'GET');

        $this->assertSame('/product/abc?utm_source=x', Attribution::landingPage($request));
    }
}
