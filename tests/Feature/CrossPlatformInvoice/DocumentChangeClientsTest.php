<?php

namespace Tests\Feature\CrossPlatformInvoice;

use App\Services\DocumentChangeCommentClient;
use App\Services\DocumentChangeRequestClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentChangeClientsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.invoice' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.connections.invoice_read' => null,
            'database.connections.invoice_write' => null,
        ]);

        DB::purge('invoice');
        DB::connection('invoice')->getPdo();

        Schema::connection('invoice')->create('document_change_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('request_uuid')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('requester_partner_id')->nullable();
            $table->string('status')->default('open');
            $table->text('requested_payload_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::connection('invoice')->create('document_change_comments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('request_id');
            $table->string('commenter_type');
            $table->unsignedBigInteger('commenter_id');
            $table->text('body');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_update_status_and_post_comment(): void
    {
        DB::connection('invoice')->table('document_change_requests')->insert([
            'id' => 1,
            'request_uuid' => 'r-1',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = app(DocumentChangeRequestClient::class)->updateStatus(1, 'in_review');
        $posted = app(DocumentChangeCommentClient::class)->post(1, '確認します。', 100);
        $comments = app(DocumentChangeRequestClient::class)->getComments(1);

        $this->assertTrue($updated);
        $this->assertTrue($posted);
        $this->assertSame('in_review', DB::connection('invoice')->table('document_change_requests')->where('id', 1)->value('status'));
        $this->assertCount(1, $comments);
        $this->assertSame('company_user', $comments[0]['commenter_type']);
        $this->assertSame('確認します。', $comments[0]['body']);
    }
}
