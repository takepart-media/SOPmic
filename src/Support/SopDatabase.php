<?php

namespace TakepartMedia\StatamicSop\Support;

class SopDatabase
{
    /**
     * Provision the dedicated SQLite file if it is missing, so the `sop`
     * connection and `php artisan migrate` work out of the box.
     *
     * Extracted from the service provider so the skip conditions are unit
     * testable without booting an application.
     *
     * @return bool Whether a file was created by this call.
     */
    public static function ensureExists(?string $path): bool
    {
        // No path at all, or an in-memory database (tests, and projects that
        // repointed the connection at a non-file driver): nothing to create.
        if (! $path || $path === ':memory:' || file_exists($path)) {
            return false;
        }

        if (! is_dir($dir = dirname($path))) {
            @mkdir($dir, 0755, true);
        }

        return touch($path);
    }
}
