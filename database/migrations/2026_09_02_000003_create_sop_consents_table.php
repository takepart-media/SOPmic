<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sop';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sop_consents')) {
            return;
        }

        Schema::connection($this->connection)->create('sop_consents', function (Blueprint $table) {
            $table->id();

            // Statamic user id — a string, and always taken from the
            // authenticated user, never from a request payload.
            $table->string('user_id');

            // Denormalised so "which SOPs has this user ever consented to" is a
            // single-table query. No FK for the same reason as the versions
            // table: consents outlive a soft-deleted SOP.
            $table->unsignedBigInteger('sop_id');

            $table->foreignId('sop_version_id')->constrained('sop_versions');

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // The legally relevant fact, kept separate from the row's own
            // creation timestamp.
            $table->timestamp('consented_at');
            $table->timestamp('created_at')->nullable();

            // The idempotency anchor: a replayed consent write hits this and is
            // swallowed by ConsentRecorder as success.
            $table->unique(['user_id', 'sop_version_id']);

            $table->index(['user_id', 'sop_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sop_consents');
    }
};
