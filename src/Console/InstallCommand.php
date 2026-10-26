<?php

namespace TakepartMedia\StatamicSop\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Statamic\Console\RunsInPlease;
use TakepartMedia\StatamicSop\Support\SopDatabase;

/**
 * One-shot bootstrapping for a fresh install: makes sure the SQLite file
 * exists, runs the addon's own migrations against the `sop` connection,
 * publishes the config so bypass roles/groups can be edited in the project,
 * and publishes the control panel script (re-run after updates).
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

    protected $description = 'Install the SOP addon (create the database, run migrations, publish the config and CP assets).';

    public function handle(): int
    {
        $this->info('SOP addon — install');

        $path = config('database.connections.sop.database');

        if (SopDatabase::ensureExists($path)) {
            $this->line("  Created database file: {$path}");
        }

        $this->line('  Running migrations on the `sop` connection...');

        // Called by class, not by name: under `php please` the console
        // application only knows Statamic's commands, and Symfony's
        // abbreviation matching resolves the name `migrate` to
        // `migrate-dates-to-utc` there. A class name skips that lookup.
        $this->call(MigrateCommand::class, [
            '--database' => 'sop',
            '--path' => __DIR__.'/../../database/migrations',
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->line('  Publishing config/sop.php...');

        $this->call(VendorPublishCommand::class, [
            '--tag' => 'sop-config',
            '--force' => false,
        ]);

        // The `sop` tag only holds the CP script (config and translations
        // have their own `sop-config` / `sop-translations` tags), so forcing
        // it is safe and makes a re-run after an update refresh the asset.
        $this->line('  Publishing control panel assets...');

        $this->call(VendorPublishCommand::class, [
            '--tag' => 'sop',
            '--force' => true,
        ]);

        $this->info('Done. Set bypass roles/groups in config/sop.php if needed.');

        return self::SUCCESS;
    }
}
