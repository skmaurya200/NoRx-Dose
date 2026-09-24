<?php

namespace Tests;

use App\Support\ManagerQuery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * The connection is checked here, after the application boots and before
     * setUpTraits() gives RefreshDatabase the chance to rebuild anything. See
     * Tests\Support\TestDatabaseGuard for why this exists.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');

        TestDatabaseGuard::check(
            $connection,
            config("database.connections.{$connection}.driver"),
            config("database.connections.{$connection}.database"),
            file_exists(base_path('bootstrap/cache/config.php')),
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function managerUrl(string $routeName, array $parameters = []): string
    {
        return ManagerQuery::url($routeName, $parameters);
    }
}
