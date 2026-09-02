<?php

namespace TakepartMedia\StatamicSop\Tests;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use TakepartMedia\StatamicSop\Support\SopDatabase;
use TakepartMedia\StatamicSop\Support\SopStatus;

class InstallationTest extends TestCase
{
    #[Test]
    public function it_merges_the_addon_config()
    {
        $this->assertTrue(config('sop.enabled'));
        $this->assertSame([], config('sop.bypass.roles'));
        $this->assertSame([], config('sop.bypass.groups'));
    }

    #[Test]
    public function it_registers_the_sop_database_connection()
    {
        $this->assertSame('sqlite', config('database.connections.sop.driver'));
        $this->assertSame(config('sop.database'), config('database.connections.sop'));
    }

    #[Test]
    public function it_creates_the_tables_on_the_sop_connection()
    {
        $this->assertTrue(Schema::connection('sop')->hasTable('sops'));
        $this->assertTrue(Schema::connection('sop')->hasTable('sop_versions'));
        $this->assertTrue(Schema::connection('sop')->hasTable('sop_consents'));

        // Not on the default connection.
        $this->assertFalse(Schema::connection(config('database.default'))->hasTable('sops'));
    }

    #[Test]
    public function migrating_again_is_a_no_op()
    {
        $this->makeSop('First', 'Body', 0);

        $this->migrateSopDatabase();

        $this->assertTrue(Schema::connection('sop')->hasTable('sops'));
        $this->assertSame(1, \TakepartMedia\StatamicSop\Models\Sop::count());
    }

    #[Test]
    public function it_registers_sop_status_as_a_scoped_binding()
    {
        $this->assertSame(app(SopStatus::class), app(SopStatus::class));
    }

    #[Test]
    public function ensure_database_exists_creates_a_missing_file()
    {
        $path = sys_get_temp_dir().'/sop-test-'.bin2hex(random_bytes(6)).'/nested/sop.sqlite';

        $this->assertFalse(file_exists($path));
        $this->assertTrue(SopDatabase::ensureExists($path));
        $this->assertTrue(file_exists($path));

        // Second call is a no-op.
        $this->assertFalse(SopDatabase::ensureExists($path));

        @unlink($path);
        @rmdir(dirname($path));
        @rmdir(dirname($path, 2));
    }

    #[Test]
    public function ensure_database_exists_skips_in_memory_and_empty_paths()
    {
        $this->assertFalse(SopDatabase::ensureExists(':memory:'));
        $this->assertFalse(SopDatabase::ensureExists(''));
        $this->assertFalse(SopDatabase::ensureExists(null));

        $this->assertFalse(file_exists(':memory:'));
    }
}
