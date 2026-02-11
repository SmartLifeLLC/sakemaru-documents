<?php

namespace Tests\Feature\CrossPlatformInvoice;

use App\Services\DocumentPublishClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentPublishClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.sakemaru' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.connections.invoice' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.connections.sakemaru_read' => null,
            'database.connections.invoice_write' => null,
        ]);

        DB::purge('sakemaru');
        DB::purge('invoice');
        DB::connection('sakemaru')->getPdo();
        DB::connection('invoice')->getPdo();

        Schema::connection('sakemaru')->create('external_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('closing_bill_id')->nullable();
            $table->date('closing_date')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('partner_code')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->unsignedBigInteger('billing_amount')->nullable();
            $table->text('metadata')->nullable();
            $table->string('status')->nullable();
            $table->string('print_type')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('document_type')->nullable();
            $table->string('file_type')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::connection('invoice')->create('documents', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('closing_bill_id')->nullable();
            $table->date('closing_date')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('partner_code')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->unsignedBigInteger('billing_amount')->nullable();
            $table->text('metadata')->nullable();
            $table->string('status')->nullable();
            $table->string('print_type')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('document_type')->nullable();
            $table->string('file_type')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_publish_and_unpublish_updates_invoice_documents(): void
    {
        DB::connection('sakemaru')->table('external_invoices')->insert([
            'id' => 1,
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'client_id' => 1,
            'partner_id' => 10,
            'closing_bill_id' => 20,
            'closing_date' => '2026-02-28',
            's3_bucket' => 'bucket',
            's3_path' => 'path/a.pdf',
            'file_name' => 'a.pdf',
            'partner_code' => 'P001',
            'partner_name' => 'A社',
            'invoice_number' => 'INV-1',
            'billing_amount' => 5000,
            'metadata' => '{"summary":{"grand_total":5000}}',
            'print_type' => 'INVOICE',
            'creator_id' => 1,
            'is_active' => 1,
            'document_type' => 'invoice',
            'file_type' => 'pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $published = app(DocumentPublishClient::class)->publish('33333333-3333-3333-3333-333333333333');
        $this->assertTrue($published);

        $saved = DB::connection('invoice')->table('documents')->where('uuid', '33333333-3333-3333-3333-333333333333')->first();
        $this->assertNotNull($saved);
        $this->assertSame('synced', $saved->status);

        $unpublished = app(DocumentPublishClient::class)->unpublish('33333333-3333-3333-3333-333333333333');
        $this->assertTrue($unpublished);

        $savedAfter = DB::connection('invoice')->table('documents')->where('uuid', '33333333-3333-3333-3333-333333333333')->first();
        $this->assertNotNull($savedAfter);
        $this->assertSame('canceled', $savedAfter->status);
        $this->assertSame(0, (int) $savedAfter->is_active);
    }
}
