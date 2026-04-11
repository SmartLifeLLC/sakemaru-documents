<?php

namespace Tests\Feature\CrossPlatformInvoice;

use App\Services\CustomInvoiceQueueClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomInvoiceQueueClientTest extends TestCase
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
            'database.connections.sakemaru_read' => null,
            'database.connections.sakemaru_write' => null,
        ]);

        DB::purge('sakemaru');
        DB::connection('sakemaru')->getPdo();

        Schema::connection('sakemaru')->create('custom_invoice_queue', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue_uuid')->unique();
            $table->string('request_type');
            $table->string('document_type');
            $table->unsignedBigInteger('target_client_id')->nullable();
            $table->unsignedBigInteger('target_partner_id')->nullable();
            $table->string('source_document_uuid')->nullable();
            $table->text('payload_json');
            $table->string('status');
            $table->string('requested_by_system');
            $table->string('requested_by_user_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_enqueue_inserts_and_get_status_returns_row(): void
    {
        $payload = [
            'request_context' => [
                'request_uuid' => '11111111-1111-1111-1111-111111111111',
                'requested_by_user_id' => 'company_user:1',
            ],
            'target' => [
                'client_id' => 1,
                'partner_id' => 10,
                'source_document_uuid' => null,
            ],
        ];

        $queueUuid = app(CustomInvoiceQueueClient::class)->enqueue($payload, 'create', 'invoice');
        $status = app(CustomInvoiceQueueClient::class)->getStatus($queueUuid);

        $this->assertSame('11111111-1111-1111-1111-111111111111', $queueUuid);
        $this->assertNotNull($status);
        $this->assertSame('queued', $status['status']);
        $this->assertSame('documents', $status['requested_by_system']);
    }

    public function test_enqueue_is_idempotent_when_same_queue_uuid_is_replayed(): void
    {
        $payload = [
            'request_context' => [
                'request_uuid' => '22222222-2222-2222-2222-222222222222',
                'requested_by_user_id' => 'company_user:1',
            ],
            'target' => [
                'client_id' => 1,
                'partner_id' => 10,
            ],
        ];

        $first = app(CustomInvoiceQueueClient::class)->enqueue($payload, 'create', 'invoice');
        $second = app(CustomInvoiceQueueClient::class)->enqueue($payload, 'create', 'invoice');

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::connection('sakemaru')->table('custom_invoice_queue')->count());
    }
}
