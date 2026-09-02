<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The dedicated, isolated database for SOPs and consents.
     */
    protected $connection = 'sop';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sops')) {
            return;
        }

        Schema::connection($this->connection)->create('sops', function (Blueprint $table) {
            $table->id();

            // Denormalised copy of the current version's title, so the listing
            // does not need to join sop_versions.
            $table->string('title');

            $table->boolean('active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            // Deliberately no foreign key: sops and sop_versions reference each
            // other, so a real FK could never be satisfied when either row is
            // inserted first. SopPublisher enforces the relation in one
            // transaction instead.
            $table->unsignedBigInteger('current_version_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sops');
    }
};
