<?php

namespace Tests\Feature\CrossPlatformInvoice;

use App\Services\CustomInvoiceJsonBuilder;
use InvalidArgumentException;
use Tests\TestCase;

class CustomInvoiceJsonBuilderTest extends TestCase
{
    public function test_build_create_payload_with_requested_by_user_format(): void
    {
        $payload = app(CustomInvoiceJsonBuilder::class)->build('create', [
            'request_uuid' => '11111111-1111-1111-1111-111111111111',
            'document_type' => 'invoice',
            'client_id' => 1,
            'partner_id' => 10,
            'issue_date' => '2026-02-11',
            'closing_date' => '2026-02-28',
            'due_date' => '2026-03-31',
            'title' => '2026年2月ご請求書',
            'partner_name' => '株式会社サンプル',
            'partner_code' => 'A001',
            'lines' => [
                [
                    'item_name' => '商品A',
                    'quantity' => 2,
                    'unit_price' => 1000,
                    'tax_rate' => 10,
                ],
            ],
        ], 123);

        $this->assertSame('1.0', $payload['schema_version']);
        $this->assertSame('create', $payload['command_type']);
        $this->assertSame('documents', $payload['request_context']['requested_by_system']);
        $this->assertSame('company_user:123', $payload['request_context']['requested_by_user_id']);
        $this->assertSame('invoice', $payload['target']['document_type']);
        $this->assertSame(2000, $payload['totals']['subtotal']);
        $this->assertSame(200, $payload['totals']['tax_total']);
        $this->assertSame(2200, $payload['totals']['grand_total']);
    }

    public function test_build_revise_requires_source_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source_uuid is required');

        app(CustomInvoiceJsonBuilder::class)->build('revise', [
            'document_type' => 'invoice',
            'issue_date' => '2026-02-11',
            'lines' => [['item_name' => 'A', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 10]],
        ], 1);
    }
}
