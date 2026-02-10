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
        Schema::connection('mysql')->create('sync_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('source_client_id');
            $table->string('source_id', 100);
            $table->string('source_code', 100)->nullable();
            $table->string('target_system', 50);
            $table->string('target_id', 100)->nullable();
            $table->enum('mapping_status', ['active', 'stale', 'conflict', 'manual_review'])->default('active');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamp('first_synced_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('stale_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['entity_type', 'source_client_id', 'source_id', 'target_system'],
                'uq_doc_sync_mappings_entity_source_target'
            );
            $table->index(['target_system', 'target_id'], 'idx_doc_sync_mappings_target');
            $table->index(['mapping_status', 'last_synced_at'], 'idx_doc_sync_mappings_status_last_synced');
            $table->index(['entity_type', 'source_client_id', 'source_code'], 'idx_doc_sync_mappings_entity_source_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('sync_mappings');
    }
};
