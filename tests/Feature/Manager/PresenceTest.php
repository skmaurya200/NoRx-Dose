<?php

namespace Tests\Feature\Manager;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CompletesSignInCode;
use Tests\TestCase;

/**
 * Which employees are signed in right now, and the pages each has opened -
 * shown to the Admin only.
 */
class PresenceTest extends TestCase
{
    use CompletesSignInCode, RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function employeeRoles(): array
    {
        return [
            'manager' => [Admin::ROLE_MANAGER],
            'user' => [Admin::ROLE_USER],
        ];
    }

    /* ------------------------------------------------------ page tracking */

    public function test_every_panel_page_an_employee_opens_is_recorded(): void
    {
        $employee = Admin::factory()->user()->create();

        $this->actingAs($employee, 'admin')->get('/manager')->assertOk();
        $this->sameBrowser();
        $this->actingAs($employee, 'admin')->get('/manager/products')->assertOk();

        $this->assertSame(
            ['Viewed page: Dashboard', 'Viewed page: Products'],
            ActivityLog::query()->where('action', ActivityLog::PAGE_VIEWED)->orderBy('id')->pluck('description')->all(),
        );

        $session = AdminSession::query()->sole();
        $this->assertSame($employee->id, $session->admin_id);
        $this->assertSame(2, $session->page_views);
        $this->assertSame('Products', $session->last_page);
        $this->assertSame('/manager/products', $session->last_url);
    }

    public function test_a_refused_page_is_not_counted_as_opened(): void
    {
        $employee = Admin::factory()->user()->create();

        $this->actingAs($employee, 'admin')->get('/manager/users')->assertForbidden();

        $this->assertDatabaseMissing('tbl_activity_logs', ['action' => ActivityLog::PAGE_VIEWED]);
    }

    public function test_the_session_id_itself_is_never_stored(): void
    {
        $employee = Admin::factory()->user()->create();

        $this->actingAs($employee, 'admin')->get('/manager')->assertOk();

        $this->assertSame(hash('sha256', session()->getId()), AdminSession::query()->sole()->session_hash);
    }

    /* ------------------------------------------------ sign-in and sign-out */

    public function test_signing_in_and_out_opens_and_closes_the_session(): void
    {
        Mail::fake();
        RateLimiter::clear('*');
        config(['admin.otp.recipients' => ['security@aurum.test']]);
        $employee = Admin::factory()->user()->create();

        $this->withCredentials()->withHeader('Referer', config('app.url').'/manager/login')
            ->postJson('/api/manager/auth/login', ['login' => $employee->username, 'password' => 'Password123!'])
            ->assertOk();
        $this->postJson('/api/manager/auth/otp/verify', ['code' => $this->sentCode()])->assertOk();
        $this->sameBrowser();

        $session = AdminSession::query()->sole();
        $this->assertNull($session->logged_out_at);
        $this->assertSame(1, AdminSession::query()->online()->count());

        $this->postJson('/api/manager/auth/logout')->assertOk();

        $this->assertNotNull($session->fresh()->logged_out_at);
        $this->assertSame(0, AdminSession::query()->online()->count());
    }

    public function test_a_deactivated_employee_is_no_longer_counted_as_signed_in(): void
    {
        $employee = Admin::factory()->user()->create();
        $this->actingAs($employee, 'admin')->get('/manager')->assertOk();

        $employee->forceFill(['is_active' => false])->save();

        $this->assertNotNull(AdminSession::query()->sole()->logged_out_at);
    }

    /* ---------------------------------------------------------- dashboard */

    public function test_the_admin_dashboard_shows_how_many_employees_are_signed_in_and_links_to_them(): void
    {
        $admin = Admin::factory()->admin()->create();
        $this->onlineSession(Admin::factory()->user()->create());
        $this->onlineSession(Admin::factory()->create());
        $twoBrowsers = Admin::factory()->user()->create();
        $this->onlineSession($twoBrowsers);
        $this->onlineSession($twoBrowsers);
        // Not counted: signed out, idle past the session lifetime, or an Admin.
        $this->onlineSession(Admin::factory()->user()->create(), ['logged_out_at' => now()]);
        $this->onlineSession(Admin::factory()->user()->create(), ['last_seen_at' => now()->subMinutes((int) config('session.lifetime') + 1)]);
        $this->onlineSession(Admin::factory()->admin()->create());

        $this->actingAs($admin, 'admin')->get('/manager')
            ->assertOk()
            ->assertSee('Employees signed in now')
            ->assertViewHas('onlineEmployees', 3)
            ->assertSee(route('manager.presence.index'));
    }

    #[DataProvider('employeeRoles')]
    public function test_an_employee_does_not_see_the_card_or_the_page_behind_it(string $role): void
    {
        $employee = Admin::factory()->create(['role' => $role]);

        $this->actingAs($employee, 'admin')->get('/manager')
            ->assertOk()
            ->assertViewHas('onlineEmployees', null)
            ->assertDontSee('Employees signed in now');

        $this->actingAs($employee, 'admin')->get('/manager/employees-online')->assertForbidden();
        $this->actingAs($employee, 'admin')->get("/manager/employees-online/{$employee->id}")->assertForbidden();
    }

    /* ------------------------------------------------------ details pages */

    public function test_the_list_shows_each_signed_in_employee_with_their_details(): void
    {
        $admin = Admin::factory()->admin()->create();
        $employee = Admin::factory()->user()->create([
            'name' => 'Asha Verma', 'username' => 'asha.verma', 'email' => 'asha@aurum.test', 'phone' => '919876543210',
        ]);
        $this->onlineSession($employee, ['page_views' => 7, 'last_page' => 'Orders', 'ip_address' => '203.0.113.7']);
        $this->onlineSession(Admin::factory()->user()->create(['name' => 'Gone Home']), ['logged_out_at' => now()]);

        $this->actingAs($admin, 'admin')->get('/manager/employees-online')
            ->assertOk()
            ->assertSee('Asha Verma')
            ->assertSee('@asha.verma')
            ->assertSee('asha@aurum.test')
            ->assertSee('919876543210')
            ->assertSee('Orders')
            ->assertSee('203.0.113.7')
            ->assertSee(route('manager.presence.show', $employee))
            ->assertDontSee('Gone Home');
    }

    public function test_the_detail_page_shows_the_pages_an_employee_has_opened(): void
    {
        $admin = Admin::factory()->admin()->create();
        $employee = Admin::factory()->user()->create(['name' => 'Asha Verma']);

        $this->actingAs($employee, 'admin')->get('/manager/orders')->assertOk();
        $this->actingAs($employee, 'admin')->get('/manager/coupons')->assertOk();

        $this->actingAs($admin, 'admin')->get("/manager/employees-online/{$employee->id}")
            ->assertOk()
            ->assertSee('Asha Verma')
            ->assertSee('Signed in now')
            ->assertSeeInOrder(['Coupons', 'Orders'])
            ->assertViewHas('pagesToday', 2);
    }

    public function test_the_list_counts_pages_each_employee_opened_today(): void
    {
        $admin = Admin::factory()->admin()->create();
        $employee = Admin::factory()->user()->create(['username' => 'asha.verma']);

        $this->actingAs($employee, 'admin')->get('/manager')->assertOk();
        $this->actingAs($employee, 'admin')->get('/manager/orders')->assertOk();
        $this->actingAs($employee, 'admin')->get('/manager/products')->assertOk();

        $this->actingAs($admin, 'admin')->get('/manager/employees-online')
            ->assertOk()
            ->assertViewHas('pagesToday', fn ($rows) => $rows->count() === 1
                && $rows->first()->admin_id === $employee->id
                && $rows->first()->pages === 3);
    }

    /**
     * Test requests do not carry the session cookie from one call to the next
     * the way a browser does, so each would otherwise look like a new browser.
     */
    private function sameBrowser(): void
    {
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function onlineSession(Admin $account, array $overrides = []): AdminSession
    {
        return AdminSession::query()->create(array_merge([
            'admin_id' => $account->id,
            'session_hash' => hash('sha256', (string) fake()->unique()->uuid()),
            'logged_in_at' => now()->subHour(),
            'last_seen_at' => now()->subMinute(),
        ], $overrides));
    }
}
