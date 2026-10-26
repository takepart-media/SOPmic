<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sop';

    private const COLUMNS = ['excluded_users', 'excluded_roles', 'excluded_groups'];

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        $missing = array_values(array_filter(
            self::COLUMNS,
            fn (string $column) => ! $schema->hasColumn('sops', $column)
        ));

        if ($missing === []) {
            return;
        }

        // Audience only, never content: none of these take part in the version
        // hash. Filtered in PHP because roles and groups live in Statamic's
        // flat files, hence no indexes.
        $schema->table('sops', function (Blueprint $table) use ($missing) {
            foreach ($missing as $column) {
                $table->json($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        $present = array_values(array_filter(
            self::COLUMNS,
            fn (string $column) => $schema->hasColumn('sops', $column)
        ));

        if ($present === []) {
            return;
        }

        $schema->table('sops', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }
};
