<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Refuses to let the suite run against anything but a throwaway database.
 *
 * RefreshDatabase drops and rebuilds every table it is pointed at, so which
 * connection it is pointed at is not a detail - it is the whole safety of
 * running the tests. phpunit.xml sets sqlite/:memory:, but those are only
 * environment variables, and a cached config file overrides them silently.
 * That is not hypothetical: it is how this project lost its admin account, its
 * journal and every saved setting.
 *
 * Kept as a plain class, apart from the base TestCase, so the decision can be
 * tested with made-up values instead of by pointing a real test run at a real
 * database to see what happens.
 */
final class TestDatabaseGuard
{
    /**
     * @throws RuntimeException when the connection is not safe to rebuild
     */
    public static function check(
        ?string $connection,
        ?string $driver,
        ?string $database,
        bool $configIsCached = false,
    ): void {
        if ($driver === 'sqlite' && in_array($database, [':memory:', ''], true)) {
            return;
        }

        throw new RuntimeException(self::message($connection, $driver, $database, $configIsCached));
    }

    private static function message(
        ?string $connection,
        ?string $driver,
        ?string $database,
        bool $configIsCached,
    ): string {
        $message = 'Refusing to run tests against the "'.($connection ?? 'unknown').'" connection ('
            .($driver ?? 'unknown').': '.($database ?? 'unknown').'). The suite rebuilds every table it '
            .'touches, so it may only run on an in-memory SQLite database.';

        // The symptom - tests reading .env instead of phpunit.xml - looks
        // nothing like the cause, so the message has to name the file to go
        // and delete.
        return $configIsCached
            ? $message.' A cached config was found at bootstrap/cache/config.php, which overrides'
                .' phpunit.xml - run `php artisan config:clear` and try again.'
            : $message;
    }
}
