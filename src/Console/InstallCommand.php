<?php

namespace TakepartMedia\StatamicSop\Console;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use TakepartMedia\StatamicSop\Support\SopDatabase;

/**
 * One-shot bootstrapping for a fresh install: makes sure the SQLite file
 * exists, runs the addon's own migrations against the `sop` connection, and
 * publishes the config so bypass roles/groups can be edited in the project.
 *
 * `RunsInPlease` is what makes `php please sop:install` resolve at all — the
 * Please application only strips the `statamic:` prefix for commands that use
 * it (see Statamic\Console\Please\Application::resolve()); without the trait
 * this would only be reachable as `php artisan statamic:sop:install`.
 */
class InstallCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:sop:install';

    protected $description = 'Install the SOP addon (create the database, run migrations, publish the config).';

    public function handle(): int
    {
        $this->info('SOP addon — install');

        $path = config('database.connections.sop.database');

        if (SopDatabase::ensureExists($path)) {
            $this->line("  Created database file: {$path}");
        }

        $this->line('  Running migrations on the `sop` connection...');

        $this->call('migrate', [
            '--database' => 'sop',
            '--path' => __DIR__.'/../../database/migrations',
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->line('  Publishing config/sop.php...');

        $this->call('vendor:publish', [
            '--tag' => 'sop-config',
            '--force' => false,
        ]);

        $this->info('Done. Set bypass roles/groups in config/sop.php if needed.');

        return self::SUCCESS;
    }
}
