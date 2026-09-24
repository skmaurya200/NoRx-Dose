<?php

namespace Tests\Feature\Policies;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Policies\ActivityLogPolicy;
use App\Policies\AdminPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The full permission matrix for User Management and the activity log. The
 * endpoint tests prove each route asks; these prove what the answer is.
 */
class AdminPolicyTest extends TestCase
{
    private function account(string $role, int $id): Admin
    {
        return (new Admin)->forceFill(['id' => $id, 'role' => $role]);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function rolesWithAccess(): array
    {
        return [
            'admin' => [Admin::ROLE_ADMIN, true],
            'manager' => [Admin::ROLE_MANAGER, false],
            'user' => [Admin::ROLE_USER, false],
            'unknown role' => ['super-duper', false],
        ];
    }

    #[DataProvider('rolesWithAccess')]
    public function test_only_the_admin_role_manages_users_and_logs(string $role, bool $allowed): void
    {
        $actor = $this->account($role, 1);
        $target = $this->account(Admin::ROLE_USER, 2);
        $users = new AdminPolicy;
        $logs = new ActivityLogPolicy;

        $this->assertSame($allowed, $users->viewAny($actor));
        $this->assertSame($allowed, $users->create($actor));
        $this->assertSame($allowed, $users->update($actor, $target));
        $this->assertSame($allowed, $users->changeStatus($actor, $target));
        $this->assertSame($allowed, $users->delete($actor, $target));
        $this->assertSame($allowed, $users->revokeDevices($actor, $target));
        $this->assertSame($allowed, $logs->viewAny($actor));
        $this->assertSame($allowed, $logs->delete($actor, new ActivityLog));
        $this->assertSame($allowed, $logs->deleteAny($actor));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function targets(): array
    {
        return [
            'a user' => [Admin::ROLE_USER, true],
            'a manager' => [Admin::ROLE_MANAGER, true],
            'another admin' => [Admin::ROLE_ADMIN, false],
        ];
    }

    #[DataProvider('targets')]
    public function test_an_admin_may_switch_off_or_delete_only_non_admin_accounts(string $targetRole, bool $allowed): void
    {
        $actor = $this->account(Admin::ROLE_ADMIN, 1);
        $target = $this->account($targetRole, 2);
        $policy = new AdminPolicy;

        $this->assertSame($allowed, $policy->changeStatus($actor, $target));
        $this->assertSame($allowed, $policy->delete($actor, $target));
        $this->assertTrue($policy->update($actor, $target));
    }

    public function test_an_admin_may_edit_but_not_switch_off_or_delete_their_own_account(): void
    {
        $actor = $this->account(Admin::ROLE_ADMIN, 1);
        $policy = new AdminPolicy;

        $this->assertTrue($policy->update($actor, $actor));
        $this->assertFalse($policy->changeStatus($actor, $actor));
        $this->assertFalse($policy->delete($actor, $actor));
    }
}
