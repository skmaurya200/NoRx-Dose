<?php

namespace App\Services\Users;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Services\Audit\ActivityLogService;
use App\Services\Auth\TrustedDeviceService;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Every write to a panel account from User Management goes through here, and
 * each one leaves an activity log entry. Passwords are hashed here and never
 * reach a log, a response or an event payload.
 *
 * Revoking trusted devices, tokens and pending codes after a password change,
 * deactivation or deletion is App\Observers\AdminObserver's job, so it also
 * happens for changes made outside this screen.
 */
class UserManagementService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly TrustedDeviceService $devices,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Admin::query()
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters) {
                $pattern = SqlLike::contains($filters['search']);

                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $pattern)
                    ->orWhere('username', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern));
            })
            ->when(
                array_key_exists((string) ($filters['role'] ?? ''), Admin::roles()),
                fn (Builder $query) => $query->where('role', $filters['role']),
            )
            ->when(in_array($filters['status'] ?? null, ['active', 'inactive'], true),
                fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->latest('id')
            ->paginate(max(5, min(100, (int) ($filters['per_page'] ?? 20))))
            ->withQueryString();
    }

    /**
     * Always creates the User role - this form cannot make an Admin or a
     * Manager, whatever is posted to it.
     *
     * @param  array{name: string, username: string, email: string, mobile_no: string, password: string}  $data
     */
    public function create(array $data, Admin $actor): Admin
    {
        return DB::transaction(function () use ($data, $actor) {
            $account = new Admin;

            $account->forceFill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['mobile_no'],
                'password' => Hash::make($data['password']),
                'role' => Admin::ROLE_USER,
                'is_active' => true,
                'password_changed_at' => Carbon::now(),
            ])->save();

            $this->activity->record(ActivityLog::USER_CREATED, 'Admin created a new user.', $actor, properties: [
                'account_id' => $account->id,
                'account_username' => $account->username,
            ]);

            return $account;
        });
    }

    /**
     * @param  array{name: string, username: string, email: string, mobile_no: string, password?: string|null, is_active?: bool|null}  $data
     */
    public function update(Admin $account, array $data, Admin $actor): Admin
    {
        return DB::transaction(function () use ($account, $data, $actor) {
            $account->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['mobile_no'],
            ]);

            $passwordChanged = filled($data['password'] ?? null);

            if ($passwordChanged) {
                $account->forceFill([
                    'password' => Hash::make($data['password']),
                    'password_changed_at' => Carbon::now(),
                ]);
            }

            $changed = array_values(array_diff(array_keys($account->getDirty()), ['password', 'password_changed_at']));

            if ($account->isDirty()) {
                $account->save();

                $this->activity->record(ActivityLog::USER_UPDATED, 'Admin updated a user account.', $actor, properties: [
                    'account_id' => $account->id,
                    'account_username' => $account->username,
                    'fields' => implode(',', $changed),
                    'password_changed' => $passwordChanged,
                ]);
            }

            if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
                $this->setActive($account, (bool) $data['is_active'], $actor);
            }

            return $account->refresh();
        });
    }

    public function setActive(Admin $account, bool $active, Admin $actor): Admin
    {
        if ($account->is_active === $active) {
            return $account;
        }

        $account->forceFill(['is_active' => $active])->save();

        $this->activity->record(
            $active ? ActivityLog::USER_ACTIVATED : ActivityLog::USER_DEACTIVATED,
            $active ? 'Admin activated a user account.' : 'Admin deactivated a user account.',
            $actor,
            properties: ['account_id' => $account->id, 'account_username' => $account->username],
        );

        return $account;
    }

    /**
     * Soft delete, like every other account removal in this project. The
     * account's activity log rows stay, still naming it.
     */
    public function delete(Admin $account, Admin $actor): void
    {
        $account->delete();

        $this->activity->record(ActivityLog::USER_DELETED, 'Admin deleted a user account.', $actor, properties: [
            'account_id' => $account->id,
            'account_username' => $account->username,
        ]);
    }

    public function revokeDevices(Admin $account, Admin $actor): int
    {
        $revoked = $this->devices->revokeAll($account);

        $this->activity->record(ActivityLog::USER_DEVICES_REVOKED, 'Admin revoked the trusted devices of a user account.', $actor, properties: [
            'account_id' => $account->id,
            'account_username' => $account->username,
            'count' => $revoked,
        ]);

        return $revoked;
    }
}
