<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_undefined_web_route_renders_the_custom_404_page(): void
    {
        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertSee('This page has wandered off.')
            ->assertSee('Back to home');
    }

    public function test_an_undefined_manager_route_links_an_admin_to_the_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/manager/this-route-does-not-exist')
            ->assertNotFound()
            ->assertSee('This page has wandered off.')
            ->assertSee('Back to dashboard');
    }

    public function test_an_undefined_api_route_keeps_the_json_error_contract(): void
    {
        $this->getJson('/api/this-route-does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Endpoint not found.');
    }
}
