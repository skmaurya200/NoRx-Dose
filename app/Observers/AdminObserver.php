<?php

namespace App\Observers;

use App\Models\Admin;
use App\Services\Audit\PresenceService;
use App\Services\Auth\LoginOtpService;
use App\Services\Auth\TrustedDeviceService;

/**
 * Credential side effects that must happen however an account is changed -
 * from User Management, from the operator's own Settings screen, or from
 * anywhere added later.
 *
 * Silent saves (the sign-in counters and the password rehash use
 * saveQuietly) do not reach here, and none of them is a credential change.
 */
class AdminObserver
{
    public function __construct(
        private readonly TrustedDeviceService $devices,
        private readonly LoginOtpService $otp,
        private readonly PresenceService $presence,
    ) {}

    public function updated(Admin $admin): void
    {
        // A new password means every browser has to prove itself again.
        if ($admin->wasChanged('password')) {
            $this->devices->revokeAll($admin);
        }

        if ($admin->wasChanged('is_active') && ! $admin->is_active) {
            $this->shutOut($admin);
        }
    }

    public function deleted(Admin $admin): void
    {
        $this->shutOut($admin);
    }

    /**
     * Revoke everything that would let the account back in without the
     * password: trusted browsers, API tokens and codes still waiting. Open
     * browser sessions are ended by EnsureAdminIsActive on their next request.
     */
    private function shutOut(Admin $admin): void
    {
        $this->devices->revokeAll($admin);
        $this->otp->invalidateFor($admin);
        $admin->tokens()->delete();

        // No longer counted as signed in on the dashboard.
        $this->presence->endAll($admin);
    }
}
