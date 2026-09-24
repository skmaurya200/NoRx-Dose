<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ManagerQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManagerQueryTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function filterTargets(): array
    {
        return [
            'analytics' => ['manager.analytics', ['touch' => 'first']],
            'blog categories' => ['manager.blog.categories.index', ['status' => 'hidden']],
            'blog posts' => ['manager.blog.index', ['status' => 'draft']],
            'product categories' => ['manager.categories.index', ['status' => 'hidden']],
            'coupons' => ['manager.coupons.index', ['type' => 'fixed']],
            'orders' => ['manager.orders.index', ['payment_status' => 'paid']],
            'products' => ['manager.products.index', ['stock' => 'low']],
            'reviews' => ['manager.reviews.index', ['rating' => '5']],
            'manager search' => ['manager.search', ['q' => 'private-search-term']],
        ];
    }

    /** @param array<string, string> $parameters */
    #[DataProvider('filterTargets')]
    public function test_each_manager_filter_redirects_to_an_encrypted_url(
        string $target,
        array $parameters,
    ): void {
        $response = $this->asAdmin()->post(route('manager.query'), [
            'target' => $target,
            ...$parameters,
        ]);

        $response->assertStatus(303);

        $location = $response->headers->get('Location');
        $this->assertIsString($location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame([ManagerQuery::PARAMETER], array_keys($query));
        $this->get($location)->assertOk();
    }

    /** @param array<string, string> $_parameters */
    #[DataProvider('filterTargets')]
    public function test_each_manager_filter_form_posts_to_the_encryption_endpoint(
        string $target,
        array $_parameters,
    ): void {
        $response = $this->asAdmin()->get(route($target));

        $response
            ->assertSee('action="'.route('manager.query').'"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="target" value="'.$target.'"', false);
    }

    public function test_an_encrypted_query_is_opaque_and_bound_to_its_route(): void
    {
        $url = ManagerQuery::url('manager.products.index', ['search' => 'private-search-term']);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $sealed = $query[ManagerQuery::PARAMETER];

        $this->assertStringNotContainsString('private-search-term', $url);
        $this->assertSame(
            ['search' => 'private-search-term'],
            ManagerQuery::open($sealed, 'manager.products.index'),
        );
        $this->assertNull(ManagerQuery::open($sealed, 'manager.orders.index'));
    }

    public function test_a_tampered_encrypted_query_returns_the_custom_404_page(): void
    {
        $url = ManagerQuery::url('manager.products.index', ['status' => 'active']);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $sealed = $query[ManagerQuery::PARAMETER];
        $tampered = substr($sealed, 0, -1).($sealed[-1] === 'a' ? 'b' : 'a');

        $this->asAdmin()
            ->get(route('manager.products.index', [ManagerQuery::PARAMETER => $tampered]))
            ->assertNotFound()
            ->assertSee('This page has wandered off.');
    }

    public function test_plaintext_manager_query_parameters_return_the_custom_404_page(): void
    {
        $this->asAdmin()
            ->get('/manager/products?status=active')
            ->assertNotFound()
            ->assertSee('This page has wandered off.');
    }

    public function test_unexpected_filter_fields_are_rejected(): void
    {
        $this->asAdmin()
            ->post(route('manager.query'), [
                'target' => 'manager.products.index',
                'status' => 'active',
                'admin' => '1',
            ])
            ->assertNotFound();
    }

    public function test_a_guest_cannot_create_an_encrypted_manager_query(): void
    {
        $this->post(route('manager.query'), [
            'target' => 'manager.products.index',
            'status' => 'active',
        ])->assertRedirect(route('manager.login'));
    }

    public function test_encrypted_filters_reach_the_manager_screen(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->draft()->create(['category_id' => $category->id, 'name' => 'Draft product']);
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Active product']);

        $this->asAdmin()
            ->get($this->managerUrl('manager.products.index', ['status' => 'draft']))
            ->assertSee('Draft product')
            ->assertDontSee('Active product');
    }

    public function test_manager_pagination_links_keep_the_page_number_encrypted(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->count(6)->create(['category_id' => $category->id]);

        $this->asAdmin()
            ->get($this->managerUrl('manager.products.index', ['per_page' => '5']))
            ->assertSee('aria-label="Pagination"', false)
            ->assertSee('?query=', false)
            ->assertDontSee('?page=', false)
            ->assertDontSee('?per_page=', false);
    }
}
