<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SyncDatabaseSetup
{
    protected function setUpSyncDatabases(): void
    {
        config([
            'database.connections.sakemaru' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.connections.mysql' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'doc_',
                'foreign_key_constraints' => false,
            ],
            'database.connections.invoice' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('sakemaru');
        DB::purge('mysql');
        DB::purge('invoice');

        DB::connection('sakemaru')->getPdo();
        DB::connection('mysql')->getPdo();
        DB::connection('invoice')->getPdo();

        $this->createSakemaruTables();
        $this->createMetaTables();
        $this->createInvoiceTables();
    }

    private function createSakemaruTables(): void
    {
        $schema = Schema::connection('sakemaru');

        $schema->create('clients', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('partners', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('client_id');
            $table->string('name')->nullable();
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('updated_at')->nullable();
        });

        $schema->create('buyers', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('partner_id');
        });

        $schema->create('buyer_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('uuid');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('closing_bill_id');
            $table->unsignedBigInteger('closing_balance_overview_id');
            $table->unsignedBigInteger('closing_balance_price_id');
            $table->date('closing_date');
            $table->string('s3_bucket');
            $table->string('s3_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('partner_code')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->unsignedBigInteger('billing_amount');
            $table->text('metadata')->nullable();
            $table->string('status');
            $table->string('print_type');
            $table->unsignedBigInteger('creator_id');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

    }

    private function createMetaTables(): void
    {
        $schema = Schema::connection('mysql');

        $schema->create('sync_runs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('sync_scope', 64);
            $table->string('mode', 32);
            $table->string('status', 32);
            $table->unsignedBigInteger('client_id')->nullable();
            $table->json('checkpoint_from')->nullable();
            $table->json('checkpoint_to')->nullable();
            $table->unsignedBigInteger('scanned_count')->default(0);
            $table->unsignedBigInteger('upsert_count')->default(0);
            $table->unsignedBigInteger('skip_count')->default(0);
            $table->unsignedBigInteger('error_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $schema->create('sync_run_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('run_id');
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('source_client_id');
            $table->string('source_pk', 128);
            $table->string('operation', 32);
            $table->string('idempotency_key', 191);
            $table->string('payload_hash', 191);
            $table->string('result_status', 32);
            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        $schema->create('sync_errors', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('run_item_id')->nullable();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('source_client_id');
            $table->string('source_pk', 128);
            $table->string('stage', 32);
            $table->string('error_code', 64);
            $table->text('error_message');
            $table->json('error_context')->nullable();
            $table->boolean('is_retryable')->default(true);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('sync_checkpoints', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('sync_scope', 64);
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('client_id');
            $table->timestamp('cursor_updated_at')->nullable();
            $table->unsignedBigInteger('cursor_id')->default(0);
            $table->unsignedBigInteger('last_run_id')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['sync_scope', 'entity_type', 'client_id'], 'sync_checkpoints_scope_entity_client_unique');
        });

        $schema->create('sync_mappings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('source_client_id');
            $table->string('source_id', 128);
            $table->string('source_code', 128)->nullable();
            $table->string('target_system', 32);
            $table->string('target_id', 128);
            $table->string('mapping_status', 32)->default('active');
            $table->decimal('confidence', 5, 2)->default(1.00);
            $table->timestamp('first_synced_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('stale_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['entity_type', 'source_client_id', 'source_id', 'target_system'],
                'sync_mappings_entity_source_target_unique'
            );
        });
    }

    private function createInvoiceTables(): void
    {
        $schema = Schema::connection('invoice');

        $schema->create('clients', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $schema->create('partners', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('client_partner_id');
            $table->unsignedBigInteger('parent_partner_id')->nullable();
            $table->string('name');
            $table->string('billing_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('can_view_group_invoices')->default(false);
            $table->timestamps();
            $table->unique(['client_id', 'client_partner_id'], 'invoice_partners_client_partner_unique');
        });

        $schema->create('buyer_invoices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('closing_bill_id');
            $table->unsignedBigInteger('closing_balance_overview_id');
            $table->unsignedBigInteger('closing_balance_price_id');
            $table->date('closing_date');
            $table->string('s3_bucket');
            $table->string('s3_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('client_code');
            $table->string('client_name');
            $table->string('partner_code')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->unsignedBigInteger('billing_amount');
            $table->text('metadata')->nullable();
            $table->string('status');
            $table->string('print_type');
            $table->unsignedBigInteger('creator_id');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
}
