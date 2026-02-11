<?php

namespace Tests\Feature\CrossPlatformInvoice;

use App\Services\ExternalInvoiceClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ExternalInvoiceClientTest extends TestCase
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
            'database.connections.sakemaru_read' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('sakemaru');
        DB::purge('sakemaru_read');
        DB::connection('sakemaru_read')->getPdo();
        $this->createExternalInvoicesTable();
    }

    public function test_get_by_uuid_returns_normalized_payload_from_database(): void
    {
        DB::connection('sakemaru_read')->table('external_invoices')->insert([
            'id' => 1,
            'uuid' => 'uuid-001',
            'client_id' => 1,
            'partner_id' => 10,
            'closing_bill_id' => 1001,
            'closing_date' => '2026-02-28',
            'billing_amount' => 43700,
            'partner_code' => 'A001',
            'partner_name' => '株式会社サンプル',
            'invoice_number' => 'INV-12345',
            'print_type' => 'INVOICE',
            'document_type' => 'invoice',
            'file_type' => 'pdf',
            'metadata' => '{"branch":{"code":"B01","name":"東京"},"salesman":{"code":"S01","name":"田中"}}',
        ]);

        $result = app(ExternalInvoiceClient::class)->getByUuid('uuid-001');

        $this->assertNotNull($result);
        $this->assertSame('uuid-001', $result['uuid']);
        $this->assertSame(1, $result['client_id']);
        $this->assertSame(1001, $result['closing_bill_id']);
        $this->assertSame('株式会社サンプル', $result['partner_name']);
        $this->assertSame('invoice', $result['document_type']);
        $this->assertSame('pdf', $result['file_type']);
        $this->assertSame('B01', $result['metadata']['branch']['code'] ?? null);
        $this->assertSame('田中', $result['metadata']['salesman']['name'] ?? null);
    }

    public function test_get_by_uuid_returns_null_when_not_found(): void
    {
        $result = app(ExternalInvoiceClient::class)->getByUuid('missing-uuid');

        $this->assertNull($result);
    }

    public function test_get_by_closing_bill_id_returns_collection_with_normalized_items_from_database(): void
    {
        DB::connection('sakemaru_read')->table('external_invoices')->insert([
            [
                'id' => 10,
                'uuid' => 'uuid-101',
                'client_id' => 1,
                'partner_id' => 20,
                'closing_bill_id' => 55,
                'closing_date' => '2026-02-28',
                'billing_amount' => 1000,
                'partner_name' => 'A社',
                'metadata' => '{"summary":{"grand_total":1000}}',
            ],
            [
                'id' => 11,
                'uuid' => 'uuid-102',
                'client_id' => 1,
                'partner_id' => 21,
                'closing_bill_id' => 55,
                'closing_date' => '2026-02-28',
                'billing_amount' => 2000,
                'partner_name' => 'B社',
                'metadata' => '{"summary":{"grand_total":2000}}',
            ],
            [
                'id' => 12,
                'uuid' => 'uuid-103',
                'client_id' => 1,
                'partner_id' => 22,
                'closing_bill_id' => 99,
                'closing_date' => '2026-02-28',
                'billing_amount' => 3000,
                'partner_name' => 'C社',
                'metadata' => '{"summary":{"grand_total":3000}}',
            ],
        ]);

        $rows = app(ExternalInvoiceClient::class)->getByClosingBillId(55);

        $this->assertCount(2, $rows);
        $this->assertSame('uuid-101', $rows[0]['uuid']);
        $this->assertSame(1000, $rows[0]['billing_amount']);
        $this->assertSame(2000, $rows[1]['metadata']['summary']['grand_total'] ?? null);
    }

    public function test_get_by_uuid_validates_empty_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('uuid is required');

        app(ExternalInvoiceClient::class)->getByUuid('  ');
    }

    public function test_get_by_closing_bill_id_validates_positive_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('closingBillId must be greater than 0');

        app(ExternalInvoiceClient::class)->getByClosingBillId(0);
    }

    private function createExternalInvoicesTable(): void
    {
        Schema::connection('sakemaru_read')->create('external_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('closing_bill_id')->nullable();
            $table->date('closing_date')->nullable();
            $table->unsignedBigInteger('billing_amount')->nullable();
            $table->string('partner_code')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('print_type')->nullable();
            $table->string('document_type')->nullable();
            $table->string('file_type')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
}
