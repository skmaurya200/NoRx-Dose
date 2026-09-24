<?php

namespace App\Policies;

use App\Models\Admin;

/**
 * User Management. Only the Admin role manages accounts; Managers and Users
 * are refused everything here, whatever the sidebar shows.
 *
 * Status changes and deletion are further limited: nobody can switch off or
 * delete their own account, and Admin accounts cannot be switched off or
 * deleted from this screen at all - both would be a way to lock every
 * administrator out of the panel.
 */
class AdminPolicy
{
    public function viewAny(Admin $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(Admin $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(Admin $actor, Admin $account): bool
    {
        return $actor->isAdmin();
    }

    public function changeStatus(Admin $actor, Admin $account): bool
    {
        return $actor->isAdmin() && ! $actor->is($account) && ! $account->isAdmin();
    }

    public function delete(Admin $actor, Admin $account): bool
    {
        return $actor->isAdmin() && ! $actor->is($account) && ! $account->isAdmin();
    }

    public function revokeDevices(Admin $actor, Admin $account): bool
    {
        return $actor->isAdmin();
    }

    /**
     * "Sign out everywhere" - ending every session and token at once is an
     * Admin's response to a compromise, not an employee's button.
     */
    public function signOutEverywhere(Admin $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Who is signed in right now, and the pages each employee has opened.
     */
    public function viewPresence(Admin $actor): bool
    {
        return $actor->isAdmin();
    }
}
