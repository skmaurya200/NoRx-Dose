<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

/**
 * The two guards that stop a development database being destroyed.
 *
 * They exist because the accident already happened: a stale config cache hid
 * phpunit.xml's sqlite settings, the suite ran RefreshDatabase against the real
 * MySQL database, and it rebuilt every table in it. Both halves are worth a
 * test, because a guard nobody exercises is a guard nobody notices has stopped
 * working.
 */
class DatabaseSafetyTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------- destructive commands */

    public function test_commands_that_drop_tables_are_blocked(): void
    {
        // What every environment but the test suite gets. Re-armed here to
        // stand in for a developer's own machine.
        DB::prohibitDestructiveCommands(true);

        $this->artisan('db:wipe')->assertFailed();
        $this->artisan('migrate:fresh')->assertFailed();
        $this->artisan('migrate:refresh')->assertFailed();
        $this->artisan('migrate:reset')->assertFailed();

        // Refused, not merely reported as failed after the damage.
        $this->assertTrue(Schema::hasTable('tbl_admins'));
    }

    public function test_forgetting_the_setting_is_the_safe_outcome(): void
    {
        $this->assertFalse(config('database.allow_destructive_commands'));
    }

    /* ----------------------------------------------------- the test database */

    public function test_the_suite_runs_on_an_in_memory_database(): void
    {
        $connection = config('database.default');

        $this->assertSame('sqlite', config("database.connections.{$connection}.driver"));
        $this->assertSame(':memory:', config("database.connections.{$connection}.database"));
    }

    public function test_an_in_memory_sqlite_connection_is_allowed(): void
    {
        TestDatabaseGuard::check('sqlite', 'sqlite', ':memory:');

        // No exception is the assertion; this says so out loud.
        $this->assertTrue(true);
    }

    public function test_a_real_connection_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests against the "mysql" connection');

        TestDatabaseGuard::check('mysql', 'mysql', 'medicure_db');
    }

    public function test_a_sqlite_file_on_disk_is_refused_too(): void
    {
        // A file is somebody's data as surely as a MySQL schema is, and
        // RefreshDatabase would rebuild it just the same.
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::check('sqlite', 'sqlite', database_path('database.sqlite'));
    }

    public function test_the_refusal_names_a_cached_config_when_there_is_one(): void
    {
        try {
            TestDatabaseGuard::check('mysql', 'mysql', 'medicure_db', configIsCached: true);
            $this->fail('The guard should have refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('config:clear', $exception->getMessage());
        }
    }

    public function test_the_refusal_stays_quiet_about_a_cache_that_is_not_there(): void
    {
        try {
            TestDatabaseGuard::check('mysql', 'mysql', 'medicure_db', configIsCached: false);
            $this->fail('The guard should have refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('config:clear', $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // The prohibition is process-wide static state, so it cannot be left
        // switched on for whatever test runs next.
        DB::prohibitDestructiveCommands(false);

        parent::tearDown();
    }
}
