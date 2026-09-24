<?php

namespace App\Services\Auth;

use App\Models\Admin;
use App\Models\TrustedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Remembers browsers that have completed the one-time code step.
 *
 * Trust only ever skips the code - AdminAuthService has already checked the
 * password before this class is asked anything. The browser holds a random
 * 64-character token in an encrypted, HttpOnly cookie; the database holds its
 * SHA-256, so neither a stolen row nor a forged plain cookie is enough.
 */
class TrustedDeviceService
{
    /**
     * The token this request presents: the cookie for a browser, or the
     * trusted_device_token field for an API client that was handed one.
     */
    public function tokenFrom(Request $request): ?string
    {
        $token = $request->cookie($this->cookieName()) ?? $request->input('trusted_device_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Whether this token was issued to this account and is still in date.
     * A token belonging to someone else is simply not trusted.
     */
    public function isTrusted(Admin $admin, ?string $token): bool
    {
        if ($token === null || strlen($token) > 128) {
            return false;
        }

        $device = TrustedDevice::query()
            ->active()
            ->where('admin_id', $admin->getKey())
            ->where('token_hash', $this->hash($token))
            ->first();

        if ($device === null) {
            return false;
        }

        $device->forceFill(['last_used_at' => Carbon::now()])->save();

        return true;
    }

    /**
     * Trust the current browser and return the raw token - the only time it
     * exists outside the browser.
     */
    public function trust(Admin $admin, Request $request): string
    {
        $token = Str::random(64);
        $now = Carbon::now();

        TrustedDevice::query()->create([
            'admin_id' => $admin->getKey(),
            'token_hash' => $this->hash($token),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'verified_at' => $now,
            'expires_at' => $now->copy()->addHours($this->hours()),
        ]);

        return $token;
    }

    /**
     * Ends trust on every browser for this account: after a password change,
     * a deactivation, a deletion, or on an administrator's request.
     */
    public function revokeAll(Admin $admin): int
    {
        return TrustedDevice::query()
            ->active()
            ->where('admin_id', $admin->getKey())
            ->update(['revoked_at' => Carbon::now()]);
    }

    /**
     * Ends trust on every browser of every panel account - an Admin's "sign out
     * everywhere". The next sign-in of anybody, Admin or employee, needs a
     * verification code again.
     */
    public function revokeEveryone(): int
    {
        return TrustedDevice::query()
            ->active()
            ->update(['revoked_at' => Carbon::now()]);
    }

    public function activeCount(Admin $admin): int
    {
        return TrustedDevice::query()->active()->where('admin_id', $admin->getKey())->count();
    }

    /**
     * Encrypted by the EncryptCookies middleware on the way out, HttpOnly so
     * no script can read it, SameSite=Lax, and Secure whenever the request is.
     */
    public function cookie(string $token, Request $request): Cookie
    {
        return cookie(
            $this->cookieName(),
            $token,
            $this->hours() * 60,
            config('session.path', '/'),
            config('session.domain'),
            config('session.secure') ?? $request->isSecure(),
            true,
            false,
            'lax',
        );
    }

    public function cookieName(): string
    {
        return (string) config('admin.trusted_device.cookie');
    }

    private function hours(): int
    {
        return max(1, (int) config('admin.trusted_device.hours'));
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
