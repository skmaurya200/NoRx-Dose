<?php

namespace App\Services\Auth;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Services\Audit\ActivityLogService;
use App\Services\Audit\PresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ending panel sessions that are not the one making the request.
 *
 * A browser session cannot be reached from the server once it has been
 * handed out - the session store is keyed by an id only the browser holds.
 * So instead of hunting sessions down, each session remembers when it signed
 * in, each account remembers when its sessions were revoked, and a session
 * older than that revocation is ended the next time it asks for anything.
 */
class PanelSessionService
{
    /**
     * Where a session keeps the moment it signed in.
     */
    public const SIGNED_IN_AT = 'admin_signed_in_at';

    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly PresenceService $presence,
        private readonly TrustedDeviceService $devices,
    ) {}

    /**
     * Stamps the session at sign-in - a password sign-in, or one restored from
     * a remember-me cookie. Called from the Login event.
     */
    public function markSignedIn(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SIGNED_IN_AT, Carbon::now()->getTimestamp());
        }
    }

    /**
     * Whether this request's session was signed in before its account's
     * sessions were revoked. A session carrying no stamp predates this check
     * and is treated as revoked once a revocation exists.
     */
    public function isRevoked(Admin $admin, Request $request): bool
    {
        if ($admin->sessions_revoked_at === null || ! $request->hasSession()) {
            return false;
        }

        $signedInAt = (int) $request->session()->get(self::SIGNED_IN_AT, 0);

        return $signedInAt < $admin->sessions_revoked_at->getTimestamp();
    }

    /**
     * Ends this request's session because it was revoked.
     */
    public function endRevoked(Request $request): void
    {
        $this->presence->end($request);

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * An Admin's "sign out everywhere": every employee - every Manager and
     * User - and every other session of the Admin's own, off the panel at once.
     *
     * Their API tokens are deleted, their remember-me cookies stop working (the
     * token behind them is replaced), and their open browser sessions end on
     * their next request. Other Admins stay signed in.
     *
     * Every trusted device of every account - employees and all Admins alike -
     * is revoked too, so nobody signs back in without a fresh verification
     * code. That is the difference from an ordinary sign-out, which leaves a
     * browser trusted for the rest of its 24 hours.
     *
     * @return int how many employees were signed out
     */
    public function signOutEveryone(Admin $actor): int
    {
        $now = Carbon::now();

        $accounts = Admin::query()
            ->where(fn ($query) => $query->where('role', '!=', Admin::ROLE_ADMIN)->orWhereKey($actor->getKey()))
            ->get();

        DB::transaction(function () use ($accounts, $now) {
            $this->devices->revokeEveryone();

            foreach ($accounts as $account) {
                // Quietly: not a credential change, so the observer's own
                // revocations do not run a second time.
                $account->forceFill([
                    'sessions_revoked_at' => $now,
                    'remember_token' => Str::random(60),
                ])->saveQuietly();

                $account->tokens()->delete();
                $this->presence->endAll($account);
            }
        });

        $employees = $accounts->reject(fn (Admin $account) => $account->is($actor))->count();

        $this->activity->record(
            ActivityLog::LOGOUT,
            "Admin signed out everywhere: {$employees} ".Str::plural('employee', $employees)
                .' signed out of the panel, and every trusted device revoked.',
            $actor,
            properties: ['everywhere' => true, 'employees_signed_out' => $employees, 'trusted_devices_revoked' => true],
        );

        return $employees;
    }
}
