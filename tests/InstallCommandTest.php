<?php

namespace TakepartMedia\StatamicSop\Tests;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class InstallCommandTest extends TestCase
{
    #[Test]
    public function it_creates_the_tables_on_the_sop_connection()
    {
        $this->dropSopTables();

        $this->assertFalse(Schema::connection('sop')->hasTable('sops'));

        $this->artisan('statamic:sop:install')->assertSuccessful();

        $this->assertTrue(Schema::connection('sop')->hasTable('sops'));
        $this->assertTrue(Schema::connection('sop')->hasTable('sop_versions'));
        $this->assertTrue(Schema::connection('sop')->hasTable('sop_consents'));
    }

    /**
     * The `statamic:` prefix is stripped for `php please ...` only for
     * commands using RunsInPlease (see
     * Statamic\Console\Please\Application::resolve()) — without the trait,
     * `sop:install` would 404 and only `statamic:sop:install` would resolve.
     * This is the closest a unit test gets to that without shelling out to
     * the `please` binary itself.
     */
    #[Test]
    public function the_install_command_uses_the_runs_in_please_trait()
    {
        $traits = class_uses(\TakepartMedia\StatamicSop\Console\InstallCommand::class);

        $this->assertArrayHasKey(\Statamic\Console\RunsInPlease::class, $traits);
    }

    #[Test]
    public function it_is_idempotent_on_a_second_run()
    {
        $this->artisan('statamic:sop:install')->assertSuccessful();

        // A second run must not blow up on "table already exists" — the
        // migrations themselves guard with hasTable(), so this only proves
        // the command can be safely re-run (e.g. after a config change).
        $this->artisan('statamic:sop:install')->assertSuccessful();

        $this->assertTrue(Schema::connection('sop')->hasTable('sops'));
    }

    private function dropSopTables(): void
    {
        $schema = Schema::connection('sop');

        $schema->dropIfExists('sop_consents');
        $schema->dropIfExists('sop_versions');
        $schema->dropIfExists('sops');

        // TestCase::setUp() already migrated once, so the migration
        // repository on this connection thinks every migration ran. Without
        // clearing it too, a fresh `migrate` call sees nothing pending and
        // skips — "Nothing to migrate" — even though the tables above are
        // gone.
        $schema->dropIfExists('migrations');
    }
}
