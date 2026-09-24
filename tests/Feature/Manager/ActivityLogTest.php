<?php

namespace Tests\Feature\Manager;

use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin Panel -> Activity Logs: viewing, filtering and deleting the audit
 * trail, and the entries existing operations leave in it.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private const SCREEN = '/manager/activity-logs';

    private const ENDPOINT = '/api/manager/activity-logs';

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'manager' => [Admin::ROLE_MANAGER],
            'user' => [Admin::ROLE_USER],
        ];
    }

    /* ------------------------------------------------------------ access */

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get(self::SCREEN)->assertRedirect('/manager/login');
        $this->deleteJson(self::ENDPOINT.'/1')->assertUnauthorized();
    }

    #[DataProvider('nonAdminRoles')]
    public function test_a_non_admin_opening_the_url_gets_403_and_cannot_delete(string $role): void
    {
        $actor = Admin::factory()->create(['role' => $role]);
        $log = ActivityLog::factory()->create();

        $this->actingAs($actor, 'admin')->get(self::SCREEN)->assertForbidden();
        $this->actingAs($actor, 'admin')->deleteJson(self::ENDPOINT."/{$log->id}")->assertForbidden();
        $this->actingAs($actor, 'admin')->postJson(self::ENDPOINT.'/bulk-delete', ['ids' => [$log->id]])->assertForbidden();

        $this->assertModelExists($log);
    }

    /* -------------------------------------------------------------- list */

    public function test_an_admin_sees_the_logs_with_their_details(): void
    {
        $admin = Admin::factory()->admin()->create();
        ActivityLog::factory()->create([
            'username' => 'riya.k',
            'action' => ActivityLog::LOGIN_SUCCESS,
            'description' => 'Signed in with password and verification code.',
            'status' => ActivityLog::STATUS_SUCCESS,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Mozilla/5.0 TestBrowser',
        ]);

        $this->actingAs($admin, 'admin')->get(self::SCREEN)
            ->assertOk()
            ->assertSee('riya.k')
            ->assertSee('LOGIN_SUCCESS')
            ->assertSee('Signed in with password and verification code.')
            ->assertSee('203.0.113.7')
            ->assertSee('Mozilla/5.0 TestBrowser');
    }

    public function test_the_list_is_paginated(): void
    {
        $admin = Admin::factory()->admin()->create();
        ActivityLog::factory()->count(26)->create();

        $this->actingAs($admin, 'admin')->get(self::SCREEN)
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 25 && $logs->total() === 26);
    }

    public function test_request_supplied_values_are_escaped(): void
    {
        $admin = Admin::factory()->admin()->create();
        ActivityLog::factory()->create([
            'username' => '<script>alert(1)</script>',
            'user_agent' => '<img src=x onerror=alert(2)>',
        ]);

        $this->actingAs($admin, 'admin')->get(self::SCREEN)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     */
    public static function filters(): array
    {
        return [
            'action' => [['action' => 'OTP_FAILED'], 'wanted-entry', 'other-entry'],
            'status' => [['status' => 'success'], 'wanted-entry', 'other-entry'],
            'role' => [['role' => Admin::ROLE_USER], 'wanted-entry', 'other-entry'],
            'ip' => [['ip' => '198.51.100.1'], 'wanted-entry', 'other-entry'],
            'search' => [['search' => 'wanted'], 'wanted-entry', 'other-entry'],
            'date range' => [['from' => '2026-09-10', 'to' => '2026-09-10'], 'wanted-entry', 'other-entry'],
        ];
    }

    /**
     * @param  array<string, string>  $filter
     */
    #[DataProvider('filters')]
    public function test_the_list_filters(array $filter, string $wanted, string $other): void
    {
        $admin = Admin::factory()->admin()->create();

        $this->travelTo('2026-09-10 12:00:00');
        ActivityLog::factory()->create([
            'description' => $wanted, 'action' => 'OTP_FAILED', 'status' => 'success',
            'role' => Admin::ROLE_USER, 'ip_address' => '198.51.100.1',
        ]);

        $this->travelTo('2026-09-12 12:00:00');
        ActivityLog::factory()->create([
            'description' => $other, 'action' => 'LOGOUT', 'status' => 'failed',
            'role' => Admin::ROLE_MANAGER, 'ip_address' => '203.0.113.9',
        ]);

        $this->actingAs($admin, 'admin')
            ->get($this->managerUrl('manager.activity-logs.index', $filter))
            ->assertOk()
            ->assertSee($wanted)
            ->assertDontSee($other);
    }

    public function test_the_list_filters_by_user(): void
    {
        $admin = Admin::factory()->admin()->create();
        $user = Admin::factory()->user()->create();
        ActivityLog::factory()->create(['admin_id' => $user->id, 'description' => 'wanted-entry']);
        ActivityLog::factory()->create(['description' => 'other-entry']);

        $this->actingAs($admin, 'admin')
            ->get($this->managerUrl('manager.activity-logs.index', ['admin_id' => $user->id]))
            ->assertOk()
            ->assertSee('wanted-entry')
            ->assertDontSee('other-entry');
    }

    public function test_a_plaintext_filter_query_is_rejected(): void
    {
        $admin = Admin::factory()->admin()->create();

        $this->actingAs($admin, 'admin')->get(self::SCREEN.'?action=LOGOUT')->assertNotFound();
    }

    /* ------------------------------------------------------------ delete */

    public function test_an_admin_deletes_one_entry_and_the_deletion_is_logged(): void
    {
        $admin = Admin::factory()->admin()->create();
        $log = ActivityLog::factory()->create();

        $this->actingAs($admin, 'admin')->deleteJson(self::ENDPOINT."/{$log->id}")->assertOk();

        $this->assertModelMissing($log);

        $record = ActivityLog::query()->sole();
        $this->assertSame(ActivityLog::ADMIN_DELETED_ACTIVITY_LOG, $record->action);
        $this->assertSame($admin->id, $record->admin_id);
    }

    public function test_an_admin_deletes_selected_entries_in_bulk(): void
    {
        $admin = Admin::factory()->admin()->create();
        [$first, $second, $kept] = ActivityLog::factory()->count(3)->create();

        $this->actingAs($admin, 'admin')
            ->postJson(self::ENDPOINT.'/bulk-delete', ['ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelExists($kept);
        $this->assertDatabaseHas('tbl_activity_logs', [
            'action' => ActivityLog::ADMIN_DELETED_ACTIVITY_LOG,
            'description' => 'Admin deleted 2 activity log entries.',
        ]);
    }

    public function test_deleting_the_deletion_record_writes_exactly_one_new_record(): void
    {
        $admin = Admin::factory()->admin()->create();
        $log = ActivityLog::factory()->create(['action' => ActivityLog::ADMIN_DELETED_ACTIVITY_LOG]);

        $this->actingAs($admin, 'admin')->deleteJson(self::ENDPOINT."/{$log->id}")->assertOk();

        $this->assertDatabaseCount('tbl_activity_logs', 1);
    }

    public function test_bulk_delete_requires_a_selection(): void
    {
        $admin = Admin::factory()->admin()->create();

        $this->actingAs($admin, 'admin')->postJson(self::ENDPOINT.'/bulk-delete', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids' => 'Select at least one log entry.']);
    }

    /* -------------------------------------------------- existing operations */

    public function test_an_existing_panel_operation_is_logged_without_its_payload(): void
    {
        $manager = Admin::factory()->create();

        $this->actingAs($manager, 'admin')->postJson('/api/manager/coupons', [
            'code' => 'SECRET10',
            'description' => 'confidential launch offer',
            'type' => 'percent',
            'value' => 10,
            'min_order_amount' => 0,
        ])->assertCreated();

        $log = ActivityLog::query()->sole();

        $this->assertSame('COUPONS_STORE', $log->action);
        $this->assertSame($manager->id, $log->admin_id);
        $this->assertSame(ActivityLog::STATUS_SUCCESS, $log->status);
        $this->assertStringNotContainsString('SECRET10', $log->toJson());
        $this->assertStringNotContainsString('confidential', $log->toJson());
    }

    public function test_a_refused_operation_is_logged_as_failed(): void
    {
        $manager = Admin::factory()->create();

        $this->actingAs($manager, 'admin')->postJson('/api/manager/coupons', [])->assertStatus(422);

        $this->assertDatabaseHas('tbl_activity_logs', ['action' => 'COUPONS_STORE', 'status' => ActivityLog::STATUS_FAILED]);
    }

    public function test_reading_does_not_write_to_the_log(): void
    {
        $manager = Admin::factory()->create();

        $this->actingAs($manager, 'admin')->getJson('/api/manager/coupons')->assertOk();

        $this->assertDatabaseCount('tbl_activity_logs', 0);
    }

    public function test_a_password_change_in_settings_is_logged_without_the_password(): void
    {
        $manager = Admin::factory()->create();

        $this->actingAs($manager, 'admin')->postJson('/api/manager/settings/password', [
            'current_password' => 'Password123!',
            'password' => 'battery-staple-7',
            'password_confirmation' => 'battery-staple-7',
        ])->assertOk();

        $log = ActivityLog::query()->sole();
        $this->assertSame('SETTINGS_PASSWORD', $log->action);
        $this->assertStringNotContainsString('battery-staple-7', $log->toJson());
        $this->assertStringNotContainsString('Password123!', $log->toJson());
    }
}
