<?php

namespace Tests\Feature\Manager;

use App\Mail\LoginOtpMail;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\LoginOtp;
use App\Models\TrustedDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CompletesSignInCode;
use Tests\TestCase;

/**
 * Username + password, then an emailed code, then a browser trusted for 24
 * hours - for every role.
 */
class OtpLoginTest extends TestCase
{
    use CompletesSignInCode, RefreshDatabase;

    private const LOGIN = '/api/manager/auth/login';

    private const VERIFY = '/api/manager/auth/otp/verify';

    private const RESEND = '/api/manager/auth/otp/resend';

    private const PASSWORD = 'Password123!';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('*');

        config(['admin.otp.recipients' => ['security@aurum.test']]);
    }

    /* -------------------------------------------------------------- helpers */

    /**
     * A same-origin browser: Sanctum starts a session for the API call only
     * when the Referer is the application's own.
     */
    private function asBrowser(?string $trustedDeviceToken = null): static
    {
        // A fresh browser carries no cookies from an earlier request.
        $this->defaultCookies = [];

        // withCredentials: JSON test requests drop cookies unless told not to,
        // and a browser's fetch() sends them with credentials: 'same-origin'.
        $this->withCredentials()->withHeader('Referer', config('app.url').'/manager/login');

        if ($trustedDeviceToken !== null) {
            $this->withCookie(config('admin.trusted_device.cookie'), $trustedDeviceToken);
        }

        return $this;
    }

    private function login(Admin $admin, string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson(self::LOGIN, ['login' => $admin->username, 'password' => $password]);
    }

    /**
     * The whole first-time sign-in in a browser. Returns the trusted-device
     * token the browser was given.
     */
    private function signInAndTrust(Admin $admin): string
    {
        Mail::fake();

        $this->asBrowser()->login($admin)->assertJsonPath('data.otp_required', true);

        $response = $this->postJson(self::VERIFY, ['code' => $this->sentCode()])->assertOk();

        return $response->getCookie(config('admin.trusted_device.cookie'))->getValue();
    }

    private function signOut(): void
    {
        $this->postJson('/api/manager/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return [
            'admin' => [Admin::ROLE_ADMIN],
            'manager' => [Admin::ROLE_MANAGER],
            'user' => [Admin::ROLE_USER],
        ];
    }

    /* ------------------------------------------------------ the first sign-in */

    #[DataProvider('roles')]
    public function test_every_role_needs_a_code_on_a_new_browser_and_is_signed_in_after_entering_it(string $role): void
    {
        Mail::fake();
        $admin = Admin::factory()->create(['role' => $role]);

        $this->asBrowser()->login($admin)
            ->assertOk()
            ->assertJsonPath('data.otp_required', true)
            ->assertJsonPath('data.redirect', route('manager.otp'));

        $this->assertGuest('admin');

        $this->postJson(self::VERIFY, ['code' => $this->sentCode()])
            ->assertOk()
            ->assertJsonPath('data.redirect', route('manager.dashboard'))
            ->assertCookie(config('admin.trusted_device.cookie'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_the_code_screen_opens_only_while_a_code_is_pending(): void
    {
        $this->get('/manager/otp')->assertRedirect('/manager/login');

        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);

        $page = $this->get('/manager/otp')
            ->assertOk()
            ->assertSee('Verify it')
            // Which inbox to open, masked: this screen is reached with a password alone.
            ->assertSee('se*****@aurum.test')
            ->assertDontSee('security@aurum.test');

        // The code is emailed, never printed on the page.
        $page->assertDontSee($this->sentCode());
    }

    public function test_the_code_is_emailed_to_the_configured_security_address_with_its_expiry(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create(['username' => 'riya.k']);

        $this->asBrowser()->login($admin);

        Mail::assertSent(LoginOtpMail::class, fn (LoginOtpMail $mail) => $mail->hasTo('security@aurum.test')
            && preg_match('/^\d{6}$/', $mail->code) === 1
            && $mail->account->is($admin)
            && $mail->expiresMinutes === 10);
    }

    public function test_without_a_configured_address_the_code_goes_to_the_active_admin_accounts(): void
    {
        Mail::fake();
        config(['admin.otp.recipients' => []]);
        Admin::factory()->admin()->create(['email' => 'owner@aurum.test']);
        Admin::factory()->admin()->inactive()->create(['email' => 'former@aurum.test']);
        $user = Admin::factory()->user()->create();

        $this->asBrowser()->login($user)->assertOk();

        Mail::assertSent(LoginOtpMail::class, fn (LoginOtpMail $mail) => $mail->hasTo('owner@aurum.test')
            && ! $mail->hasTo('former@aurum.test'));
    }

    public function test_sign_in_stops_with_503_when_there_is_nowhere_to_send_the_code(): void
    {
        Mail::fake();
        config(['admin.otp.recipients' => []]);
        $manager = Admin::factory()->create();

        $this->asBrowser()->login($manager)
            ->assertStatus(503)
            ->assertJsonPath('message', 'The verification code could not be sent. Please contact the administrator.');

        $this->assertGuest('admin');
        Mail::assertNothingSent();
    }

    public function test_the_email_escapes_the_account_name_and_never_contains_the_password(): void
    {
        $admin = Admin::factory()->make(['name' => '<script>alert(1)</script>', 'username' => 'riya.k']);

        $html = (new LoginOtpMail($admin, '123456', 10, Carbon::parse('2026-09-15 10:00:00'), '203.0.113.7', 'NoRx Dose'))->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('expire in 10 minutes', $html);
        $this->assertStringNotContainsString(self::PASSWORD, $html);
    }

    /* ---------------------------------------------------- wrong credentials */

    public function test_a_wrong_password_is_refused_and_no_code_is_generated(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();

        $this->asBrowser()->login($admin, 'WrongPassword1!')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid username or password.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('tbl_login_otps', 0);
    }

    public function test_an_inactive_account_is_refused_with_the_right_password(): void
    {
        Mail::fake();
        $admin = Admin::factory()->inactive()->create();

        $this->asBrowser()->login($admin)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account is inactive. Please contact the administrator.');

        Mail::assertNothingSent();
    }

    /* ---------------------------------------------------------- wrong codes */

    public function test_a_wrong_code_is_refused(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);
        $wrong = $this->sentCode() === '000000' ? '111111' : '000000';

        $this->postJson(self::VERIFY, ['code' => $wrong])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid or expired OTP.');

        $this->assertGuest('admin');
    }

    public function test_an_expired_code_is_refused(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);
        $code = $this->sentCode();

        $this->travel(11)->minutes();

        $this->postJson(self::VERIFY, ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid or expired OTP.');

        $this->assertGuest('admin');
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);
        $challenge = session('admin_otp.challenge');
        $code = $this->sentCode();

        $this->postJson(self::VERIFY, ['code' => $code])->assertOk();

        $this->postJson(self::VERIFY, ['code' => $code, 'challenge_token' => $challenge, 'device_name' => 'replay'])
            ->assertStatus(422);

        $this->assertDatabaseCount('tbl_personal_access_tokens', 0);
    }

    public function test_the_code_is_burned_after_the_attempt_limit_even_if_the_right_code_follows(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i < 5; $i++) {
            $this->postJson(self::VERIFY, ['code' => $wrong])->assertStatus(422);
        }

        $this->postJson(self::VERIFY, ['code' => $wrong])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts. Please try again later.');

        $this->postJson(self::VERIFY, ['code' => $code])->assertStatus(422);
        $this->assertGuest('admin');
    }

    public function test_an_account_deactivated_while_its_code_is_pending_cannot_finish_signing_in(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);
        $code = $this->sentCode();

        $admin->forceFill(['is_active' => false])->saveQuietly();

        $this->postJson(self::VERIFY, ['code' => $code])->assertStatus(403);
        $this->assertGuest('admin');
    }

    /* --------------------------------------------------------------- resend */

    public function test_resending_replaces_the_code_so_only_the_newest_one_works(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);
        $old = $this->sentCode();

        $this->travel(61)->seconds();
        Mail::fake();

        $this->postJson(self::RESEND)->assertOk();
        $new = $this->sentCode();

        if ($old !== $new) {
            $this->postJson(self::VERIFY, ['code' => $old])->assertStatus(422);
        }

        $this->postJson(self::VERIFY, ['code' => $new])->assertOk();
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_a_second_sign_in_invalidates_the_first_code(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();

        $this->asBrowser()->login($admin);
        $first = LoginOtp::query()->sole();

        $this->asBrowser()->login($admin);

        $this->assertNotNull($first->fresh()->invalidated_at);
        $this->assertSame(1, LoginOtp::query()->open()->count());
    }

    public function test_a_resend_inside_the_cooldown_is_refused_with_429(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);

        $this->postJson(self::RESEND)
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        Mail::assertSentCount(1);
    }

    public function test_resends_stop_after_the_limit(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $this->asBrowser()->login($admin);

        for ($i = 0; $i < 3; $i++) {
            $this->travel(61)->seconds();
            $this->postJson(self::RESEND)->assertOk();
        }

        $this->travel(61)->seconds();

        $this->postJson(self::RESEND)->assertStatus(429);
        Mail::assertSentCount(4);
    }

    public function test_repeated_sign_ins_cannot_flood_the_security_mailbox(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->asBrowser()->login($admin)->assertOk();
        }

        $this->asBrowser()->login($admin)->assertStatus(429);
        Mail::assertSentCount(5);
    }

    /* ------------------------------------------------------ trusted devices */

    public function test_a_trusted_browser_signs_in_again_after_logout_without_a_code(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->signInAndTrust($admin);
        $this->signOut();

        Mail::fake();

        $this->asBrowser($token)->login($admin)
            ->assertOk()
            ->assertJsonPath('data.redirect', route('manager.dashboard'))
            ->assertJsonMissingPath('data.otp_required');

        Mail::assertNothingSent();
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_a_trusted_browser_still_needs_the_password(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $token = $this->signInAndTrust($admin);
        $this->signOut();

        $this->asBrowser($token)->login($admin, 'WrongPassword1!')->assertStatus(401);

        $this->assertGuest('admin');
    }

    public function test_trust_ends_after_24_hours_and_the_code_is_required_again(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->signInAndTrust($admin);
        $this->signOut();

        $this->travel(24)->hours();
        $this->travel(1)->minutes();
        Mail::fake();

        $this->asBrowser($token)->login($admin)->assertJsonPath('data.otp_required', true);

        Mail::assertSentCount(1);
    }

    public function test_trust_belongs_to_one_browser_not_to_the_account(): void
    {
        $admin = Admin::factory()->create();
        $this->signInAndTrust($admin);
        $this->signOut();

        Mail::fake();

        $this->asBrowser()->login($admin)->assertJsonPath('data.otp_required', true);
    }

    public function test_a_trusted_device_token_from_another_account_is_not_trusted(): void
    {
        $owner = Admin::factory()->create();
        $token = $this->signInAndTrust($owner);
        $this->signOut();
        $other = Admin::factory()->create();

        Mail::fake();

        $this->asBrowser($token)->login($other)->assertJsonPath('data.otp_required', true);
    }

    public function test_a_forged_cookie_is_not_trusted(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();

        $this->asBrowser('otp_verified=true')->login($admin)->assertJsonPath('data.otp_required', true);
    }

    public function test_only_a_hash_of_the_trusted_device_token_is_stored(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->signInAndTrust($admin);

        $device = TrustedDevice::query()->sole();

        $this->assertSame(hash('sha256', $token), $device->token_hash);
        $this->assertTrue($device->expires_at->equalTo($device->verified_at->copy()->addHours(24)));
    }

    public function test_changing_the_password_ends_trust_on_every_browser(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->signInAndTrust($admin);
        $this->signOut();

        $admin->forceFill(['password' => 'NewPassword456!'])->save();
        Mail::fake();

        $this->asBrowser($token)->login($admin, 'NewPassword456!')->assertJsonPath('data.otp_required', true);
    }

    public function test_an_api_client_can_be_trusted_with_the_token_it_was_given(): void
    {
        $admin = Admin::factory()->create();
        $credentials = ['login' => $admin->username, 'password' => self::PASSWORD, 'device_name' => 'cli'];

        $deviceToken = $this->signInForToken($credentials)->assertOk()->json('data.trusted_device_token');
        Mail::fake();

        $this->postJson(self::LOGIN, $credentials + ['trusted_device_token' => $deviceToken])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        Mail::assertNothingSent();
    }

    /* ------------------------------------------------------------- logging */

    public function test_the_whole_flow_is_logged_without_the_code_or_the_password(): void
    {
        $admin = Admin::factory()->create();
        Mail::fake();
        $this->asBrowser()->login($admin, 'WrongPassword1!');
        $this->login($admin);
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->postJson(self::VERIFY, ['code' => $wrong]);
        $this->postJson(self::VERIFY, ['code' => $code]);
        $this->postJson('/api/manager/auth/logout');

        $this->assertSame(
            ['LOGIN_FAILED', 'OTP_SENT', 'OTP_FAILED', 'OTP_VERIFIED', 'LOGIN_SUCCESS', 'LOGOUT'],
            ActivityLog::query()->orderBy('id')->pluck('action')->all(),
        );

        $success = ActivityLog::query()->where('action', 'LOGIN_SUCCESS')->sole();
        $this->assertSame($admin->id, $success->admin_id);
        $this->assertSame($admin->role, $success->role);
        $this->assertTrue($success->properties['otp_required']);
        $this->assertSame('password_otp', $success->properties['login_method']);

        $failed = ActivityLog::query()->where('action', 'LOGIN_FAILED')->sole();
        $this->assertNull($failed->admin_id);
        $this->assertSame($admin->username, $failed->username);
        $this->assertSame(ActivityLog::STATUS_FAILED, $failed->status);

        $everything = ActivityLog::query()->get()->toJson();
        $this->assertStringNotContainsString($code, $everything);
        $this->assertStringNotContainsString(self::PASSWORD, $everything);
        $this->assertStringNotContainsString('WrongPassword1!', $everything);
    }

    public function test_a_direct_sign_in_on_a_trusted_browser_is_logged_as_not_needing_a_code(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->signInAndTrust($admin);
        $this->signOut();

        $this->asBrowser($token)->login($admin)->assertOk();

        $last = ActivityLog::query()->where('action', 'LOGIN_SUCCESS')->latest('id')->first();
        $this->assertFalse($last->properties['otp_required']);
        $this->assertSame('password_trusted_device', $last->properties['login_method']);
    }
}
