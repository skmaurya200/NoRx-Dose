<?php

namespace Tests\Feature\Manager;

use App\Mail\LoginOtpMail;
use App\Models\Admin;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\Auth\PanelSessionService;
use App\Services\Auth\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CompletesSignInCode;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CompletesSignInCode, RefreshDatabase;

    private const LOGIN = '/api/manager/auth/login';

    protected function setUp(): void
    {
        parent::setUp();

        // Each test starts with a clean throttle, otherwise the brute-force
        // tests below would leak into the ones that follow.
        RateLimiter::clear('*');

        // A correct password now leads to an emailed verification code, which
        // needs somewhere to go.
        config(['admin.otp.recipients' => ['security@aurum.test']]);
    }

    /**
     * Laravel keeps a resolved guard (and the user on it) for the lifetime of
     * the test application, so a second request in the same test would reuse
     * the first request's user and never re-check the token. Production makes
     * one request per process, so this only matters here.
     */
    private function forgetResolvedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /* ---------------------------------------------------------------- validation */

    public function test_login_requires_both_fields(): void
    {
        $this->postJson(self::LOGIN, [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors'])
            ->assertJsonValidationErrors(['login', 'password']);
    }

    public function test_login_rejects_a_short_password_without_touching_the_database(): void
    {
        Admin::factory()->create(['email' => 'boss@aurum.test']);

        $this->postJson(self::LOGIN, ['login' => 'boss@aurum.test', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    /* --------------------------------------------------------------- credentials */

    public function test_a_wrong_password_is_rejected(): void
    {
        Admin::factory()->create(['email' => 'boss@aurum.test']);

        $this->postJson(self::LOGIN, ['login' => 'boss@aurum.test', 'password' => 'WrongPassword1'])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_an_unknown_account_returns_exactly_the_same_answer_as_a_wrong_password(): void
    {
        Admin::factory()->create(['email' => 'boss@aurum.test']);

        $wrongPassword = $this->postJson(self::LOGIN, [
            'login' => 'boss@aurum.test', 'password' => 'WrongPassword1',
        ]);

        $unknownAccount = $this->postJson(self::LOGIN, [
            'login' => 'ghost@aurum.test', 'password' => 'WrongPassword1',
        ]);

        // Any difference here would let an attacker enumerate valid admins.
        $this->assertSame($wrongPassword->status(), $unknownAccount->status());
        $this->assertSame(
            $wrongPassword->json('message'),
            $unknownAccount->json('message'),
        );
    }

    public function test_an_admin_can_sign_in_with_a_phone_number(): void
    {
        Mail::fake();
        Admin::factory()->create(['phone' => '919876543210']);

        $this->postJson(self::LOGIN, [
            'login' => '+91 98765 43210',
            'password' => 'Password123!',
            'device_name' => 'phone-test',
        ])->assertOk()->assertJsonPath('success', true);
    }

    /* -------------------------------------------------------------- account state */

    public function test_a_disabled_account_cannot_sign_in_even_with_the_right_password(): void
    {
        Admin::factory()->inactive()->create(['email' => 'boss@aurum.test']);

        $this->postJson(self::LOGIN, ['login' => 'boss@aurum.test', 'password' => 'Password123!'])
            ->assertStatus(403);
    }

    public function test_a_locked_account_is_refused_even_with_the_right_password(): void
    {
        Admin::factory()->locked()->create(['email' => 'boss@aurum.test']);

        $this->postJson(self::LOGIN, ['login' => 'boss@aurum.test', 'password' => 'Password123!'])
            ->assertStatus(423)
            ->assertHeader('Retry-After');
    }

    public function test_repeated_failures_lock_the_account(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'boss@aurum.test',
            'failed_login_attempts' => config('admin.lockout.threshold') - 1,
        ]);

        $this->postJson(self::LOGIN, ['login' => 'boss@aurum.test', 'password' => 'WrongPassword1'])
            ->assertStatus(401);

        $this->assertTrue($admin->fresh()->isLocked());
    }

    public function test_a_successful_sign_in_clears_the_failure_counter(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create([
            'email' => 'boss@aurum.test',
            'failed_login_attempts' => 3,
        ]);

        $this->postJson(self::LOGIN, [
            'login' => 'boss@aurum.test',
            'password' => 'Password123!',
            'device_name' => 'test',
        ])->assertOk();

        $fresh = $admin->fresh();
        $this->assertSame(0, $fresh->failed_login_attempts);
        $this->assertNotNull($fresh->last_login_at);
    }

    /* ------------------------------------------------------------------ throttling */

    public function test_the_endpoint_throttles_after_the_configured_attempts(): void
    {
        Admin::factory()->create(['email' => 'boss@aurum.test']);

        $max = (int) config('admin.login.max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->postJson(self::LOGIN, ['login' => 'boss@aurum.test', 'password' => 'WrongPassword1'])
                ->assertStatus(401);
        }

        $this->postJson(self::LOGIN, ['login' => 'boss@aurum.test', 'password' => 'WrongPassword1'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    /* ---------------------------------------------------------------------- tokens */

    public function test_a_token_is_issued_when_a_device_name_is_supplied(): void
    {
        Admin::factory()->create(['email' => 'boss@aurum.test']);

        // The token only exists after the verification code step.
        $response = $this->signInForToken([
            'login' => 'boss@aurum.test',
            'password' => 'Password123!',
            'device_name' => 'mobile',
        ])->assertOk();

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame('Bearer', $response->json('data.token_type'));
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertDatabaseCount('tbl_personal_access_tokens', 1);
    }

    public function test_the_password_is_never_returned_in_any_response(): void
    {
        Admin::factory()->create(['email' => 'boss@aurum.test']);

        $response = $this->signInForToken([
            'login' => 'boss@aurum.test',
            'password' => 'Password123!',
            'device_name' => 'mobile',
        ])->assertOk();

        $body = $response->getContent();

        $this->assertStringNotContainsString('Password123!', $body);
        $this->assertStringNotContainsString('password', $response->json('data.admin') === null ? '' : json_encode($response->json('data.admin')));
    }

    public function test_signing_in_again_from_the_same_device_replaces_the_old_token(): void
    {
        Admin::factory()->create(['email' => 'boss@aurum.test']);

        $payload = ['login' => 'boss@aurum.test', 'password' => 'Password123!', 'device_name' => 'mobile'];

        $first = $this->signInForToken($payload)->json('data.token');
        $this->signInForToken($payload)->assertOk();

        $this->assertDatabaseCount('tbl_personal_access_tokens', 1);

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$first}")
            ->getJson('/api/manager/auth/me')
            ->assertStatus(401);
    }

    /* ---------------------------------------------------------------------- logout */

    public function test_logout_revokes_only_the_current_token(): void
    {
        $admin = Admin::factory()->create();

        $keep = $admin->createToken('other-device')->plainTextToken;
        $drop = $admin->createToken('this-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$drop}")
            ->postJson('/api/manager/auth/logout')
            ->assertOk();

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$drop}")
            ->getJson('/api/manager/auth/me')
            ->assertStatus(401);

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$keep}")
            ->getJson('/api/manager/auth/me')
            ->assertOk();
    }

    public function test_logout_all_revokes_every_token(): void
    {
        // Sign out everywhere is an Admin action.
        $admin = Admin::factory()->admin()->create();

        $a = $admin->createToken('device-a')->plainTextToken;
        $b = $admin->createToken('device-b')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$a}")
            ->postJson('/api/manager/auth/logout-all')
            ->assertOk();

        $this->assertDatabaseCount('tbl_personal_access_tokens', 0);

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$b}")
            ->getJson('/api/manager/auth/me')
            ->assertStatus(401);
    }

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

    #[DataProvider('employeeRoles')]
    public function test_an_employee_cannot_sign_out_everywhere_and_is_not_offered_it(string $role): void
    {
        $employee = Admin::factory()->create(['role' => $role]);
        $other = $employee->createToken('other-device')->plainTextToken;

        $this->actingAs($employee, 'admin')->get('/manager')
            ->assertOk()
            ->assertDontSee('data-logout-all', false)
            ->assertSee('data-logout', false);

        $this->withHeader('Authorization', "Bearer {$other}")
            ->postJson('/api/manager/auth/logout-all')
            ->assertForbidden();

        $this->assertDatabaseCount('tbl_personal_access_tokens', 1);
    }

    public function test_an_admin_is_offered_sign_out_everywhere(): void
    {
        $this->actingAs(Admin::factory()->admin()->create(), 'admin')
            ->get('/manager')
            ->assertOk()
            ->assertSee('data-logout-all', false);
    }

    /* ------------------------------------------- sign out everywhere, all staff */

    public function test_signing_out_everywhere_signs_every_employee_out_of_the_panel(): void
    {
        $admin = Admin::factory()->admin()->create();
        $manager = Admin::factory()->create();
        $user = Admin::factory()->user()->create();
        $managerToken = $manager->createToken('cli')->plainTextToken;
        $rememberToken = $user->getRememberToken();

        $this->actingAs($admin, 'admin')
            ->postJson('/api/manager/auth/logout-all')
            ->assertOk()
            ->assertJsonPath('data.employees_signed_out', 2);

        foreach ([$manager, $user] as $employee) {
            $this->forgetResolvedUser();

            // A browser session this employee opened before the sign-out. Fresh
            // from the database, as a real request would load it.
            $this->actingAs($employee->fresh(), 'admin')
                ->withSession([PanelSessionService::SIGNED_IN_AT => now()->subMinute()->getTimestamp()])
                ->get('/manager')
                ->assertRedirect('/manager/login');

            $this->assertGuest('admin');
        }

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$managerToken}")
            ->getJson('/api/manager/auth/me')
            ->assertUnauthorized();

        $this->assertNotSame($rememberToken, $user->fresh()->getRememberToken());
        $this->assertDatabaseHas('tbl_activity_logs', ['action' => 'LOGOUT', 'admin_id' => $admin->id]);
    }

    /**
     * An ordinary sign-out keeps a browser trusted for its 24 hours. Sign out
     * everywhere does not: every account - employee or Admin, including the one
     * who pressed it - needs a fresh code at its next sign-in.
     */
    public function test_after_signing_out_everywhere_nobody_signs_in_without_a_code(): void
    {
        $actor = Admin::factory()->admin()->create();
        $otherAdmin = Admin::factory()->admin()->create();
        $manager = Admin::factory()->create();
        $user = Admin::factory()->user()->create();

        $devices = app(TrustedDeviceService::class);
        $tokens = [];

        foreach ([$actor, $otherAdmin, $manager, $user] as $account) {
            $tokens[$account->id] = $devices->trust($account, Request::create('/'));
        }

        $this->actingAs($actor, 'admin')->postJson('/api/manager/auth/logout-all')->assertOk();

        $this->assertSame(0, TrustedDevice::query()->active()->count());

        Mail::fake();

        foreach ([$actor, $otherAdmin, $manager, $user] as $account) {
            $this->forgetResolvedUser();

            $this->postJson(self::LOGIN, [
                'login' => $account->username,
                'password' => 'Password123!',
                'device_name' => 'cli',
                'trusted_device_token' => $tokens[$account->id],
            ])->assertOk()->assertJsonPath('data.otp_required', true);
        }

        // Each code went to the configured security address.
        Mail::assertSent(LoginOtpMail::class, 4);
        Mail::assertSent(LoginOtpMail::class, fn (LoginOtpMail $mail) => $mail->hasTo('security@aurum.test'));
    }

    public function test_an_ordinary_sign_out_does_not_revoke_any_trusted_device(): void
    {
        $employee = Admin::factory()->user()->create();
        $token = $employee->createToken('cli')->plainTextToken;
        $deviceToken = app(TrustedDeviceService::class)->trust($employee, Request::create('/'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/auth/logout')
            ->assertOk();

        $this->forgetResolvedUser();
        Mail::fake();

        $this->postJson(self::LOGIN, [
            'login' => $employee->username,
            'password' => 'Password123!',
            'device_name' => 'cli',
            'trusted_device_token' => $deviceToken,
        ])->assertOk()->assertJsonPath('data.token_type', 'Bearer');

        Mail::assertNothingSent();
    }

    public function test_a_signed_out_employees_open_panel_gets_401_from_the_api(): void
    {
        $employee = Admin::factory()->user()->create();

        $this->actingAs(Admin::factory()->admin()->create(), 'admin')
            ->postJson('/api/manager/auth/logout-all')
            ->assertOk();

        $this->forgetResolvedUser();

        $this->actingAs($employee->fresh(), 'admin')
            ->withSession([PanelSessionService::SIGNED_IN_AT => now()->subMinute()->getTimestamp()])
            ->withHeader('Referer', config('app.url').'/manager')
            ->getJson('/api/manager/coupons')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'You have been signed out. Please sign in again.');
    }

    public function test_an_employee_who_signs_in_again_afterwards_is_let_in(): void
    {
        $employee = Admin::factory()->user()->create();

        $this->actingAs(Admin::factory()->admin()->create(), 'admin')
            ->postJson('/api/manager/auth/logout-all')
            ->assertOk();

        $this->forgetResolvedUser();
        $this->travel(5)->seconds();

        $this->actingAs($employee->fresh(), 'admin')
            ->withSession([PanelSessionService::SIGNED_IN_AT => now()->getTimestamp()])
            ->get('/manager')
            ->assertOk();
    }

    public function test_other_admins_are_not_signed_out(): void
    {
        $otherAdmin = Admin::factory()->admin()->create();
        $token = $otherAdmin->createToken('cli')->plainTextToken;

        $this->actingAs(Admin::factory()->admin()->create(), 'admin')
            ->postJson('/api/manager/auth/logout-all')
            ->assertOk()
            ->assertJsonPath('data.employees_signed_out', 0);

        $this->assertNull($otherAdmin->fresh()->sessions_revoked_at);

        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/manager/auth/me')->assertOk();
    }

    public function test_a_panel_sign_in_stamps_its_session(): void
    {
        Mail::fake();
        $employee = Admin::factory()->user()->create();

        $this->withCredentials()->withHeader('Referer', config('app.url').'/manager/login')
            ->postJson(self::LOGIN, ['login' => $employee->username, 'password' => 'Password123!'])
            ->assertOk();

        $this->freezeTime();

        $this->postJson('/api/manager/auth/otp/verify', ['code' => $this->sentCode()])->assertOk();

        $this->assertSame(now()->getTimestamp(), session(PanelSessionService::SIGNED_IN_AT));
    }

    /* ---------------------------------------------------------------- dashboard */

    public function test_the_admin_dashboard_puts_all_five_cards_in_one_row(): void
    {
        $this->actingAs(Admin::factory()->admin()->create(), 'admin')
            ->get('/manager')
            ->assertOk()
            ->assertSee('class="col-6 col-md-4 col-xl"', false)
            ->assertDontSee('class="col-6 col-xl-3"', false);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/manager')
            ->assertOk()
            ->assertSee('class="col-6 col-xl-3"', false)
            ->assertDontSee('class="col-6 col-md-4 col-xl"', false);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/manager/auth/logout')->assertStatus(401);
        $this->postJson('/api/manager/auth/logout-all')->assertStatus(401);
    }

    /* ------------------------------------------------------------------ boundaries */

    public function test_a_storefront_customer_cannot_reach_the_admin_api(): void
    {
        // sanctum.guard lists the web guard, so a signed-in customer does
        // authenticate - EnsureAdmin is what must stop them.
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->getJson('/api/manager/auth/me')
            ->assertStatus(403);
    }

    public function test_a_token_stops_working_the_moment_the_account_is_disabled(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/auth/me')
            ->assertOk();

        $admin->forceFill(['is_active' => false])->saveQuietly();
        $this->forgetResolvedUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/auth/me')
            ->assertStatus(403);

        // and the token it presented is burned
        $this->assertDatabaseCount('tbl_personal_access_tokens', 0);
    }

    public function test_a_soft_deleted_admin_cannot_sign_in(): void
    {
        $admin = Admin::factory()->create(['email' => 'boss@aurum.test']);
        $admin->delete();

        $this->postJson(self::LOGIN, ['login' => 'boss@aurum.test', 'password' => 'Password123!'])
            ->assertStatus(401);
    }

    /* ---------------------------------------------------------------- panel pages */

    public function test_the_panel_redirects_a_guest_to_the_login_screen(): void
    {
        $this->get('/manager')->assertRedirect('/manager/login');
        $this->get('/manager/orders')->assertRedirect('/manager/login');
    }

    public function test_the_login_screen_is_reachable_by_a_guest(): void
    {
        $this->get('/manager/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_a_signed_in_admin_is_bounced_off_the_login_screen(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/manager/login')
            ->assertRedirect('/manager');
    }

    public function test_a_signed_in_admin_can_open_the_panel(): void
    {
        $admin = Admin::factory()->create(['name' => 'Riya Kapoor']);

        $this->actingAs($admin, 'admin')
            ->get('/manager')
            ->assertOk()
            ->assertSee('Riya Kapoor');
    }
}
