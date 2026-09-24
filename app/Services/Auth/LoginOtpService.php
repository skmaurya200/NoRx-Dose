<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AccountDisabledException;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpDeliveryException;
use App\Exceptions\Auth\TooManyAttemptsException;
use App\Mail\LoginOtpMail;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\LoginOtp;
use App\Services\Audit\ActivityLogService;
use App\Support\Settings\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one-time code step of signing in.
 *
 * A challenge is a random 64-character value handed to the caller (kept in
 * the session for a browser). The code is six digits from random_int(), which
 * draws on the operating system's CSPRNG. Neither is stored in the clear, and
 * neither is ever logged or returned in a response.
 *
 * A challenge has one live code at a time: re-sending replaces the code, and
 * starting a new sign-in invalidates every earlier challenge for the account.
 */
class LoginOtpService
{
    /**
     * Where a browser's pending challenge waits between the password and the
     * code. Server-side session storage, never a cookie or the page.
     */
    public const SESSION_KEY = 'admin_otp';

    public function __construct(private readonly ActivityLogService $activity) {}

    /**
     * Start a challenge for an account whose password has just been verified,
     * and email its code. Returns the raw challenge.
     *
     * @throws TooManyAttemptsException
     * @throws OtpDeliveryException
     */
    public function issue(Admin $admin, Request $request): string
    {
        $limiterKey = 'admin-otp-issue:'.$admin->getKey();

        if (RateLimiter::tooManyAttempts($limiterKey, max(1, (int) config('admin.otp.issue_limit')))) {
            throw new TooManyAttemptsException(RateLimiter::availableIn($limiterKey));
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            $this->activity->record(ActivityLog::OTP_SENT, 'Verification code could not be sent: no security recipient is configured.',
                $admin, ActivityLog::STATUS_FAILED);

            throw new OtpDeliveryException;
        }

        RateLimiter::hit($limiterKey, max(1, (int) config('admin.otp.issue_window_seconds')));

        $challenge = Str::random(64);
        $code = $this->generateCode();
        $now = Carbon::now();

        $otp = DB::transaction(function () use ($admin, $request, $challenge, $code, $now) {
            LoginOtp::query()
                ->where('admin_id', $admin->getKey())
                ->whereNull('used_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => $now]);

            return LoginOtp::query()->create([
                'admin_id' => $admin->getKey(),
                'challenge_hash' => $this->challengeHash($challenge),
                'code_hash' => $this->codeHash($challenge, $code),
                'attempts' => 0,
                'send_count' => 1,
                'last_sent_at' => $now,
                'expires_at' => $now->copy()->addMinutes($this->expiresMinutes()),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
        });

        $this->deliver($otp, $admin, $code, $recipients, $request);

        return $challenge;
    }

    /**
     * Replace the code on a live challenge and send the new one. The earlier
     * code stops working the moment this returns.
     *
     * @throws InvalidOtpException
     * @throws TooManyAttemptsException
     * @throws OtpDeliveryException
     */
    public function resend(string $challenge, Request $request): LoginOtp
    {
        $otp = $this->pending($challenge);
        $admin = $otp?->admin;

        if ($otp === null || $admin === null || ! $admin->is_active) {
            throw new InvalidOtpException;
        }

        if ($otp->send_count - 1 >= (int) config('admin.otp.max_resends')) {
            $this->activity->record(ActivityLog::OTP_SENT, 'Verification code re-send refused: re-send limit reached.',
                $admin, ActivityLog::STATUS_FAILED);

            throw new TooManyAttemptsException(max(1, (int) Carbon::now()->diffInSeconds($otp->expires_at)));
        }

        $cooldownEnds = $otp->last_sent_at?->copy()->addSeconds((int) config('admin.otp.resend_cooldown_seconds'));

        if ($cooldownEnds !== null && $cooldownEnds->isFuture()) {
            throw new TooManyAttemptsException(max(1, (int) ceil(Carbon::now()->diffInSeconds($cooldownEnds))));
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            throw new OtpDeliveryException;
        }

        $code = $this->generateCode();
        $now = Carbon::now();

        $otp->forceFill([
            'code_hash' => $this->codeHash($challenge, $code),
            'send_count' => $otp->send_count + 1,
            'last_sent_at' => $now,
            'expires_at' => $now->copy()->addMinutes($this->expiresMinutes()),
        ])->save();

        $this->deliver($otp, $admin, $code, $recipients, $request, resent: true);

        return $otp;
    }

    /**
     * Check a code against a challenge and return the account it proves.
     *
     * The row is locked for the check, so two parallel guesses cannot both
     * read the same attempt count, and a correct code cannot be spent twice.
     *
     * @throws InvalidOtpException
     * @throws TooManyAttemptsException
     * @throws AccountDisabledException
     */
    public function verify(string $challenge, string $code): Admin
    {
        $maxAttempts = max(1, (int) config('admin.otp.max_attempts'));

        // Decided inside the transaction, thrown outside it: throwing inside
        // would roll back the attempt counter it just incremented.
        [$outcome, $admin] = DB::transaction(function () use ($challenge, $code, $maxAttempts) {
            /** @var LoginOtp|null $otp */
            $otp = LoginOtp::query()
                ->where('challenge_hash', $this->challengeHash($challenge))
                ->lockForUpdate()
                ->first();

            if ($otp === null) {
                return ['unknown', null];
            }

            $admin = Admin::query()->find($otp->admin_id);

            if (! $otp->isOpen()) {
                return ['closed', $admin];
            }

            if ($admin === null || ! $admin->is_active) {
                $otp->forceFill(['invalidated_at' => Carbon::now()])->save();

                return [$admin === null ? 'closed' : 'inactive', $admin];
            }

            if (! hash_equals($otp->code_hash, $this->codeHash($challenge, $code))) {
                $attempts = $otp->attempts + 1;

                $otp->forceFill([
                    'attempts' => $attempts,
                    'invalidated_at' => $attempts >= $maxAttempts ? Carbon::now() : null,
                ])->save();

                return [$attempts >= $maxAttempts ? 'exhausted' : 'wrong', $admin];
            }

            $otp->forceFill(['used_at' => Carbon::now()])->save();

            return ['verified', $admin];
        });

        if ($outcome === 'verified') {
            $this->activity->record(ActivityLog::OTP_VERIFIED, 'Sign-in verification code accepted.', $admin);

            return $admin;
        }

        $this->activity->record(ActivityLog::OTP_FAILED, match ($outcome) {
            'wrong' => 'Incorrect verification code entered.',
            'exhausted' => 'Incorrect verification code entered; attempt limit reached and the code was invalidated.',
            'inactive' => 'Verification refused: the account is inactive.',
            default => 'Verification attempted with an expired, used or unknown code.',
        }, $admin, ActivityLog::STATUS_FAILED, ['reason' => $outcome]);

        throw match ($outcome) {
            'exhausted' => new TooManyAttemptsException(60),
            'inactive' => new AccountDisabledException,
            default => new InvalidOtpException,
        };
    }

    /**
     * The live challenge behind a raw value, or null.
     */
    public function pending(?string $challenge): ?LoginOtp
    {
        if (! is_string($challenge) || $challenge === '' || strlen($challenge) > 128) {
            return null;
        }

        return LoginOtp::query()
            ->open()
            ->where('challenge_hash', $this->challengeHash($challenge))
            ->first();
    }

    /**
     * Invalidate a challenge the caller has walked away from.
     */
    public function cancel(?string $challenge): void
    {
        $this->pending($challenge)?->forceFill(['invalidated_at' => Carbon::now()])->save();
    }

    /**
     * Ends every open challenge for an account - on deactivation or deletion.
     */
    public function invalidateFor(Admin $admin): void
    {
        LoginOtp::query()
            ->where('admin_id', $admin->getKey())
            ->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => Carbon::now()]);
    }

    public function expiresMinutes(): int
    {
        return max(1, (int) config('admin.otp.expires_minutes'));
    }

    /**
     * OTP_SECURITY_EMAIL when it is set; otherwise every active Admin's own
     * address. Never an address written into the code.
     *
     * @return array<int, string>
     */
    public function recipients(): array
    {
        $configured = array_values(array_filter(
            (array) config('admin.otp.recipients'),
            fn (mixed $address) => is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if ($configured !== []) {
            return $configured;
        }

        return Admin::query()
            ->where('role', Admin::ROLE_ADMIN)
            ->where('is_active', true)
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Where the code went, as the verification screen shows it:
     *
     * "so*****@gmail.com". Masked because that screen is reached with a
     * password alone, before the code has proved anything - enough for the
     * person signing in to know which inbox to open, not enough to hand a
     * stranger the security address.
     *
     * @return array<int, string>
     */
    public function maskedRecipients(): array
    {
        return array_map(function (string $address): string {
            [$local, $domain] = array_pad(explode('@', $address, 2), 2, '');

            return mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1))).'*****@'.$domain;
        }, $this->recipients());
    }

    /**
     * @param  array<int, string>  $recipients
     *
     * @throws OtpDeliveryException
     */
    private function deliver(LoginOtp $otp, Admin $admin, string $code, array $recipients, Request $request, bool $resent = false): void
    {
        try {
            Mail::to($recipients)->send(new LoginOtpMail(
                $admin,
                $code,
                $this->expiresMinutes(),
                Carbon::now(),
                $request->ip(),
                Site::name(),
            ));
        } catch (Throwable $exception) {
            $otp->forceFill(['invalidated_at' => Carbon::now()])->save();

            // The class and message only: a transport exception can quote the
            // SMTP conversation, but never the message body.
            report($exception);

            $this->activity->record(ActivityLog::OTP_SENT, 'Verification code could not be delivered.',
                $admin, ActivityLog::STATUS_FAILED);

            throw new OtpDeliveryException;
        }

        $this->activity->record(
            ActivityLog::OTP_SENT,
            $resent ? 'Sign-in verification code re-sent.' : 'Sign-in verification code sent.',
            $admin,
            properties: ['resent' => $resent, 'recipients' => count($recipients)],
        );
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    private function challengeHash(string $challenge): string
    {
        return hash('sha256', $challenge);
    }

    /**
     * Keyed with the application key and bound to the challenge, so the
     * million possible codes cannot be precomputed from a database dump.
     */
    private function codeHash(string $challenge, string $code): string
    {
        return hash_hmac('sha256', $challenge.'|'.$code, (string) config('app.key'));
    }
}
