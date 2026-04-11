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
        if (! Schema::connection('mysql')->hasTable('sync_errors')) {
            Schema::connection('mysql')->create('sync_errors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('run_id');
                $table->unsignedBigInteger('run_item_id')->nullable();
                $table->string('entity_type', 50);
                $table->unsignedBigInteger('source_client_id');
                $table->string('source_pk', 100);
                $table->string('stage', 50);
                $table->string('error_code', 100);
                $table->text('error_message');
                $table->json('error_context')->nullable();
                $table->boolean('is_retryable')->default(false);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['run_id', 'last_seen_at'], 'idx_doc_sync_errors_run_last_seen');
                $table->index(['resolved_at', 'is_retryable', 'last_seen_at'], 'idx_doc_sync_errors_resolved_retry_last');
                $table->index(['entity_type', 'source_client_id', 'source_pk'], 'idx_doc_sync_errors_entity_source');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('sync_errors');
    }
};
