<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AccountDisabledException;
use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\TooManyAttemptsException;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Services\Audit\ActivityLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * All admin sign-in rules live here, so the API controller and the Blade form
 * cannot drift apart on security behaviour. Nothing in this class touches the
 * session or issues tokens - the caller decides how the verified admin is
 * turned into a session or a bearer token.
 */
class AdminAuthService
{
    /**
     * A bcrypt hash of a value nobody can supply. Used to spend the same time
     * hashing when the account does not exist, so response timing does not
     * reveal which emails are registered.
     */
    private const DUMMY_HASH = '$2y$12$86ol0EhozjsWLljpQ4GeAeaq7JM1V/Rn75vuOVtvi18KFSXfWNwqi';

    public function __construct(private readonly ActivityLogService $activity) {}

    /**
     * Verify a set of credentials and return the admin behind them.
     *
     * @throws TooManyAttemptsException
     * @throws AccountLockedException
     * @throws InvalidCredentialsException
     * @throws AccountDisabledException
     */
    public function attempt(string $login, string $password, string $ip): Admin
    {
        $throttleKey = $this->throttleKey($login, $ip);

        $this->ensureNotRateLimited($throttleKey, $login, $ip);

        $admin = $this->findByLogin($login);

        if ($admin === null) {
            // Burn the same work as a real check before failing.
            Hash::check($password, self::DUMMY_HASH);
            RateLimiter::hit($throttleKey, config('admin.login.decay_seconds'));

            $this->logFailure($login, $ip, 'unknown_account');

            throw new InvalidCredentialsException;
        }

        // Checked before the password so a locked account cannot be probed.
        if ($admin->isLocked()) {
            $this->logFailure($login, $ip, 'locked_account', $admin->id);

            throw new AccountLockedException($admin->secondsUntilUnlock());
        }

        if (! Hash::check($password, $admin->password)) {
            $this->registerFailure($admin);
            RateLimiter::hit($throttleKey, config('admin.login.decay_seconds'));

            $this->logFailure($login, $ip, 'bad_password', $admin->id);

            throw new InvalidCredentialsException;
        }

        // Only after the password is proven, so this never leaks account state
        // to someone who does not already hold the credentials.
        if (! $admin->is_active) {
            $this->logFailure($login, $ip, 'inactive_account', $admin->id);

            throw new AccountDisabledException;
        }

        RateLimiter::clear($throttleKey);
        $this->registerSuccess($admin, $ip);

        return $admin;
    }

    /**
     * Rehash on sign-in when the configured work factor has moved on.
     */
    public function rehashIfNeeded(Admin $admin, string $password): void
    {
        if (! Hash::needsRehash($admin->password)) {
            return;
        }

        $admin->forceFill(['password' => Hash::make($password)])->saveQuietly();
    }

    /**
     * Look the account up by username, email or phone, ignoring soft-deleted
     * rows. Username is the primary identifier; email and phone keep working
     * for accounts created before usernames existed. A username must contain a
     * letter, so it can never be mistaken for a phone number.
     */
    private function findByLogin(string $login): ?Admin
    {
        $login = trim($login);

        if (filter_var($login, FILTER_VALIDATE_EMAIL) !== false) {
            return Admin::query()->where('email', Str::lower($login))->first();
        }

        $byUsername = Admin::query()->where('username', Str::lower($login))->first();

        if ($byUsername !== null) {
            return $byUsername;
        }

        $phone = $this->normalisePhone($login);

        return $phone === '' ? null : Admin::query()->where('phone', $phone)->first();
    }

    /**
     * Strip formatting so "+91 98765 43210" matches a stored "919876543210".
     */
    private function normalisePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * @throws TooManyAttemptsException
     */
    private function ensureNotRateLimited(string $key, string $login, string $ip): void
    {
        if (! RateLimiter::tooManyAttempts($key, config('admin.login.max_attempts'))) {
            return;
        }

        $this->logFailure($login, $ip, 'throttled');

        throw new TooManyAttemptsException(RateLimiter::availableIn($key));
    }

    /**
     * Bump the account's failure counter and lock it once the threshold is hit.
     * Done in a transaction with a row lock so parallel guesses cannot race the
     * counter and slip past the threshold.
     */
    private function registerFailure(Admin $admin): void
    {
        DB::transaction(function () use ($admin): void {
            /** @var Admin $fresh */
            $fresh = Admin::query()->lockForUpdate()->find($admin->getKey());

            if ($fresh === null) {
                return;
            }

            $attempts = $fresh->failed_login_attempts + 1;
            $threshold = (int) config('admin.lockout.threshold');

            $fresh->forceFill([
                'failed_login_attempts' => $attempts,
                'locked_until' => $attempts >= $threshold
                    ? Carbon::now()->addMinutes((int) config('admin.lockout.minutes'))
                    : $fresh->locked_until,
            ])->saveQuietly();
        });
    }

    private function registerSuccess(Admin $admin, string $ip): void
    {
        $admin->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $ip,
        ])->saveQuietly();
    }

    /**
     * Keyed on identifier and IP together: one attacker cannot lock every
     * admin out by hammering the endpoint from a single address, and one
     * account cannot be hammered from a single address either.
     */
    private function throttleKey(string $login, string $ip): string
    {
        return 'admin-login:'.sha1(Str::lower(trim($login)).'|'.$ip);
    }

    /**
     * Never logs the submitted password, and logs the identifier only so a
     * real lockout can be traced.
     *
     * The activity log row carries the attempted identifier and the reason for
     * the panel's administrators; the caller only ever sees the generic
     * "Invalid username or password." message.
     */
    private function logFailure(string $login, string $ip, string $reason, ?int $adminId = null): void
    {
        $identifier = Str::limit(Str::lower(trim($login)), 60);

        Log::channel(config('logging.default'))->warning('Admin sign-in failed', [
            'reason' => $reason,
            'identifier' => $identifier,
            'ip' => $ip,
            'admin_id' => $adminId,
        ]);

        $this->activity->record(
            ActivityLog::LOGIN_FAILED,
            'Failed sign-in attempt.',
            status: ActivityLog::STATUS_FAILED,
            properties: ['reason' => $reason, 'account_id' => $adminId],
            username: $identifier,
        );
    }
}
