<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sop';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sop_versions')) {
            return;
        }

        Schema::connection($this->connection)->create('sop_versions', function (Blueprint $table) {
            $table->id();

            // No cascade: versions are the audit trail and outlive the SOP,
            // which is only ever soft deleted.
            $table->foreignId('sop_id')->constrained('sops');

            $table->unsignedInteger('version_no');
            $table->string('title');
            $table->text('content');

            // sha256(title . "\0" . content) — the change detector that decides
            // whether a save bumps the version.
            $table->string('content_hash', 64)->index();

            // Statamic user id, nullable because a version may be written by a
            // console command with no authenticated user.
            $table->string('created_by')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(['sop_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sop_versions');
    }
};
