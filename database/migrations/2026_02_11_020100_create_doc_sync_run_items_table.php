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
        Schema::connection('mysql')->create('sync_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('source_client_id');
            $table->string('source_pk', 100);
            $table->enum('operation', ['insert', 'update', 'skip']);
            $table->char('idempotency_key', 64);
            $table->char('payload_hash', 64);
            $table->enum('result_status', ['success', 'failed', 'skipped']);
            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['run_id', 'entity_type', 'source_client_id', 'source_pk'],
                'uq_doc_sync_run_items_run_entity_source'
            );
            $table->index(['run_id', 'result_status'], 'idx_doc_sync_run_items_run_status');
            $table->index(['entity_type', 'source_client_id', 'source_pk'], 'idx_doc_sync_run_items_entity_source');
            $table->index(['idempotency_key'], 'idx_doc_sync_run_items_idem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('sync_run_items');
    }
};
