<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sakemaru';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sakemaru')->create('doc_sync_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('sync_scope', 100);
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('client_id');
            $table->timestamp('cursor_updated_at')->nullable();
            $table->unsignedBigInteger('cursor_id')->default(0);
            $table->unsignedBigInteger('last_run_id')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['sync_scope', 'entity_type', 'client_id'], 'uq_doc_sync_checkpoints_scope_entity_client');
            $table->index(['last_run_id'], 'idx_doc_sync_checkpoints_last_run');
            $table->index(['updated_at'], 'idx_doc_sync_checkpoints_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sakemaru')->dropIfExists('doc_sync_checkpoints');
    }
};
