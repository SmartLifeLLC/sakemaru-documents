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
        if (! Schema::connection('mysql')->hasTable('sync_runs')) {
            Schema::connection('mysql')->create('sync_runs', function (Blueprint $table) {
                $table->id();
                $table->string('sync_scope', 100);
                $table->enum('mode', ['dry_run', 'apply'])->default('dry_run');
                $table->enum('status', ['running', 'success', 'failed', 'partial'])->default('running');
                $table->unsignedBigInteger('client_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->json('checkpoint_from')->nullable();
                $table->json('checkpoint_to')->nullable();
                $table->unsignedBigInteger('scanned_count')->default(0);
                $table->unsignedBigInteger('upsert_count')->default(0);
                $table->unsignedBigInteger('skip_count')->default(0);
                $table->unsignedBigInteger('error_count')->default(0);
                $table->timestamps();

                $table->index(['sync_scope', 'created_at'], 'idx_doc_sync_runs_scope_created');
                $table->index(['status', 'created_at'], 'idx_doc_sync_runs_status_created');
                $table->index(['client_id', 'created_at'], 'idx_doc_sync_runs_client_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('sync_runs');
    }
};
