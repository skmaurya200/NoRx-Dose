<?php

namespace Tests\Feature\Manager;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\TrustedDevice;
use App\Services\Auth\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Users -> Add / Edit / Activate / Deactivate / Delete, Admin role only.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/users';

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('*');
    }

    private function asAdmin(): static
    {
        $this->admin ??= Admin::factory()->admin()->create();

        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Asha Verma',
            'username' => 'asha.verma',
            'email' => 'asha@aurum.test',
            'mobile_no' => '+91 98765 43210',
            'password' => 'Str0ng!Pass',
        ], $overrides);
    }

    private function trustBrowserFor(Admin $account): void
    {
        app(TrustedDeviceService::class)->trust($account, Request::create('/'));
    }

    /* ---------------------------------------------------------- access */

    public function test_the_endpoints_are_closed_to_guests(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();
        $this->get('/manager/users')->assertRedirect('/manager/login');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'manager' => [Admin::ROLE_MANAGER],
            'user' => [Admin::ROLE_USER],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_a_non_admin_cannot_add_a_user_or_open_the_screens(string $role): void
    {
        $actor = Admin::factory()->create(['role' => $role]);

        $this->actingAs($actor, 'admin')->postJson(self::ENDPOINT, $this->payload())->assertForbidden();
        $this->actingAs($actor, 'admin')->get('/manager/users')->assertForbidden();
        $this->actingAs($actor, 'admin')->get('/manager/users/create')->assertForbidden();

        $this->assertDatabaseMissing('tbl_admins', ['username' => 'asha.verma']);
    }

    public function test_a_user_cannot_edit_deactivate_or_delete_another_account(): void
    {
        $actor = Admin::factory()->user()->create();
        $other = Admin::factory()->user()->create();

        $this->actingAs($actor, 'admin')
            ->postJson(self::ENDPOINT."/{$other->id}", $this->payload())->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->patchJson(self::ENDPOINT."/{$other->id}/toggle")->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->deleteJson(self::ENDPOINT."/{$other->id}")->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->get("/manager/users/{$other->id}/edit")->assertForbidden();

        $this->assertNotSoftDeleted($other);
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_the_sidebar_shows_the_new_modules_to_an_admin_only(): void
    {
        $this->asAdmin()->get('/manager')->assertOk()->assertSee('Activity logs')->assertSee(route('manager.users.index'));

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/manager')->assertOk()->assertDontSee(route('manager.users.index'));
    }

    /* ----------------------------------------------------------- create */

    public function test_an_admin_creates_a_user_with_a_hashed_password_and_the_user_role(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, $this->payload(['username' => '  Asha.Verma ', 'role' => Admin::ROLE_ADMIN]))
            ->assertCreated()
            ->assertJsonPath('data.username', 'asha.verma')
            ->assertJsonPath('data.role', Admin::ROLE_USER)
            ->assertJsonMissingPath('data.password');

        $account = Admin::query()->where('username', 'asha.verma')->sole();

        $this->assertSame(Admin::ROLE_USER, $account->role);
        $this->assertSame('919876543210', $account->phone);
        $this->assertTrue($account->is_active);
        $this->assertNotSame('Str0ng!Pass', $account->getRawOriginal('password'));
        $this->assertTrue(Hash::check('Str0ng!Pass', $account->password));
    }

    public function test_creating_a_user_is_logged_without_the_password(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $log = ActivityLog::query()->where('action', ActivityLog::USER_CREATED)->sole();

        $this->assertSame($this->admin->id, $log->admin_id);
        $this->assertSame('Admin created a new user.', $log->description);
        $this->assertStringNotContainsString('Str0ng!Pass', ActivityLog::query()->get()->toJson());
    }

    public function test_the_add_user_form_has_exactly_the_five_fields(): void
    {
        $this->asAdmin()->get('/manager/users/create')
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="mobile_no"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('name="role"', false)
            ->assertDontSee('name="is_active"', false)
            ->assertSee('Create User');
    }

    public function test_every_field_is_required(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'username', 'email', 'mobile_no', 'password']);
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function invalidFields(): array
    {
        return [
            'username with spaces' => ['username', 'asha verma', 'Use letters, numbers, dots, dashes or underscores, with at least one letter.'],
            'username of digits only' => ['username', '9876543210', 'Use letters, numbers, dots, dashes or underscores, with at least one letter.'],
            'invalid email' => ['email', 'not-an-email', 'The email field must be a valid email address.'],
            'invalid mobile' => ['mobile_no', '12345', 'Enter a valid mobile number of 10 to 15 digits.'],
            'mobile with letters' => ['mobile_no', '98765abcde', 'Enter a valid mobile number of 10 to 15 digits.'],
            'weak password' => ['password', 'password', 'The password field must contain at least one uppercase and one lowercase letter.'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_an_invalid_value_is_refused_with_a_message(string $field, mixed $value, string $message): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, $this->payload([$field => $value]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field => $message]);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
     */
    public static function duplicates(): array
    {
        return [
            'username' => ['username', ['username' => 'asha.verma'], 'That username is already taken.'],
            'email' => ['email', ['email' => 'asha@aurum.test'], 'An account with that email address already exists.'],
            'mobile' => ['mobile_no', ['phone' => '919876543210'], 'An account with that mobile number already exists.'],
        ];
    }

    /**
     * @param  array<string, string>  $existing
     */
    #[DataProvider('duplicates')]
    public function test_a_duplicate_is_refused(string $field, array $existing, string $message): void
    {
        Admin::factory()->create($existing);

        $this->asAdmin()->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field => $message]);
    }

    /* ------------------------------------------------------------- update */

    public function test_an_admin_edits_a_user_and_it_is_logged(): void
    {
        $account = Admin::factory()->user()->create();

        $this->asAdmin()
            ->postJson(self::ENDPOINT."/{$account->id}", $this->payload(['password' => '']))
            ->assertOk()
            ->assertJsonPath('data.email', 'asha@aurum.test');

        $this->assertDatabaseHas('tbl_admins', ['id' => $account->id, 'username' => 'asha.verma', 'phone' => '919876543210']);
        $this->assertTrue(Hash::check('Password123!', $account->fresh()->password));
        $this->assertDatabaseHas('tbl_activity_logs', ['action' => ActivityLog::USER_UPDATED, 'admin_id' => $this->admin->id]);
    }

    public function test_an_account_keeps_its_own_username_and_email_on_edit(): void
    {
        $account = Admin::factory()->user()->create(['username' => 'asha.verma', 'email' => 'asha@aurum.test', 'phone' => '919876543210']);

        $this->asAdmin()
            ->postJson(self::ENDPOINT."/{$account->id}", $this->payload())
            ->assertOk();
    }

    public function test_changing_a_password_revokes_that_accounts_trusted_devices(): void
    {
        $account = Admin::factory()->user()->create();
        $this->trustBrowserFor($account);

        $this->asAdmin()
            ->postJson(self::ENDPOINT."/{$account->id}", $this->payload(['password' => 'N3w!Password']))
            ->assertOk();

        $this->assertTrue(Hash::check('N3w!Password', $account->fresh()->password));
        $this->assertSame(0, TrustedDevice::query()->active()->count());
        $this->assertStringNotContainsString('N3w!Password', ActivityLog::query()->get()->toJson());
    }

    public function test_an_admin_can_revoke_a_users_trusted_devices(): void
    {
        $account = Admin::factory()->user()->create();
        $this->trustBrowserFor($account);

        $this->asAdmin()
            ->deleteJson(self::ENDPOINT."/{$account->id}/trusted-devices")
            ->assertOk()
            ->assertJsonPath('data.revoked', 1);

        $this->assertSame(0, TrustedDevice::query()->active()->count());
    }

    /* ------------------------------------------------------------- status */

    public function test_a_deactivated_user_cannot_sign_in_and_loses_trust_and_tokens(): void
    {
        Mail::fake();
        $account = Admin::factory()->user()->create();
        $this->trustBrowserFor($account);
        $account->createToken('cli');

        $this->asAdmin()
            ->patchJson(self::ENDPOINT."/{$account->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, TrustedDevice::query()->active()->count());
        $this->assertDatabaseCount('tbl_personal_access_tokens', 0);
        $this->assertDatabaseHas('tbl_activity_logs', ['action' => ActivityLog::USER_DEACTIVATED]);

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/manager/auth/login', ['login' => $account->username, 'password' => 'Password123!', 'device_name' => 'cli'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is inactive. Please contact the administrator.');

        Mail::assertNothingSent();
    }

    public function test_a_reactivated_user_goes_through_the_code_step_again(): void
    {
        Mail::fake();
        config(['admin.otp.recipients' => ['security@aurum.test']]);
        $account = Admin::factory()->user()->inactive()->create();

        $this->asAdmin()->patchJson(self::ENDPOINT."/{$account->id}/toggle")->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('tbl_activity_logs', ['action' => ActivityLog::USER_ACTIVATED]);

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/manager/auth/login', ['login' => $account->username, 'password' => 'Password123!', 'device_name' => 'cli'])
            ->assertOk()
            ->assertJsonPath('data.otp_required', true);
    }

    public function test_an_open_panel_session_ends_once_the_account_is_deactivated(): void
    {
        $account = Admin::factory()->user()->create();

        $account->forceFill(['is_active' => false])->saveQuietly();

        $this->actingAs($account, 'admin')->get('/manager')->assertRedirect('/manager/login');
        $this->assertGuest('admin');
    }

    public function test_an_admin_cannot_deactivate_or_delete_their_own_or_another_admin_account(): void
    {
        $otherAdmin = Admin::factory()->admin()->create();
        $this->asAdmin();

        $this->patchJson(self::ENDPOINT."/{$this->admin->id}/toggle")->assertForbidden();
        $this->deleteJson(self::ENDPOINT."/{$this->admin->id}")->assertForbidden();
        $this->patchJson(self::ENDPOINT."/{$otherAdmin->id}/toggle")->assertForbidden();
        $this->deleteJson(self::ENDPOINT."/{$otherAdmin->id}")->assertForbidden();

        $this->postJson(self::ENDPOINT."/{$this->admin->id}", $this->payload(['status_present' => '1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_active' => 'The status of this account cannot be changed here.']);

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertNotSoftDeleted($otherAdmin);
    }

    /* ------------------------------------------------------------- delete */

    public function test_an_admin_deletes_a_user_who_then_cannot_sign_in(): void
    {
        $account = Admin::factory()->user()->create();
        ActivityLog::factory()->create(['admin_id' => $account->id, 'username' => $account->username]);

        $this->asAdmin()->deleteJson(self::ENDPOINT."/{$account->id}")->assertOk();

        $this->assertSoftDeleted($account);
        $this->assertDatabaseHas('tbl_activity_logs', ['action' => ActivityLog::USER_DELETED]);
        // The deleted account's own history is kept.
        $this->assertDatabaseHas('tbl_activity_logs', ['admin_id' => $account->id, 'action' => ActivityLog::LOGIN_FAILED]);

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/manager/auth/login', ['login' => $account->username, 'password' => 'Password123!', 'device_name' => 'cli'])
            ->assertUnauthorized();
    }

    /* ------------------------------------------------------------ screens */

    public function test_the_list_shows_accounts_without_passwords_and_escapes_names(): void
    {
        Admin::factory()->user()->create(['name' => '<b>Bold</b> Asha', 'username' => 'asha.verma']);

        $this->asAdmin()->get('/manager/users')
            ->assertOk()
            ->assertSee('@asha.verma')
            ->assertSee('&lt;b&gt;Bold&lt;/b&gt; Asha', false)
            ->assertDontSee('<b>Bold</b> Asha', false)
            ->assertDontSee('$2y$', false);
    }

    public function test_the_list_filters_by_role_through_a_sealed_query(): void
    {
        Admin::factory()->user()->create(['name' => 'Only User']);
        Admin::factory()->create(['name' => 'Only Manager']);

        $this->asAdmin()->get($this->managerUrl('manager.users.index', ['role' => Admin::ROLE_USER]))
            ->assertOk()
            ->assertSee('Only User')
            ->assertDontSee('Only Manager');
    }

    public function test_the_edit_screen_opens_for_an_admin(): void
    {
        $account = Admin::factory()->user()->create(['name' => 'Asha Verma']);

        $this->asAdmin()->get("/manager/users/{$account->id}/edit")->assertOk()->assertSee('Asha Verma');
    }
}
