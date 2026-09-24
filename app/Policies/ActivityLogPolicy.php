<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\Admin;

/**
 * The audit trail is readable and deletable by the Admin role only.
 */
class ActivityLogPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isAdmin();
    }

    public function delete(Admin $actor, ActivityLog $activityLog): bool
    {
        return $actor->isAdmin();
    }

    public function deleteAny(Admin $actor): bool
    {
        return $actor->isAdmin();
    }
}
