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

        Schema::connection('sakemaru')->create('partners', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('bill_group_id')->nullable();
            $table->string('name_main');
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

        Schema::connection('invoice')->create('partners', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('client_partner_id');
            $table->unsignedBigInteger('parent_partner_id')->nullable();
            $table->string('name');
            $table->string('billing_email')->nullable();
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('can_view_group_invoices')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['client_id', 'client_partner_id']);
        });

        Schema::connection('invoice')->create('partner_users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('partner_id');
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_publish_and_unpublish_updates_invoice_documents(): void
    {
        DB::connection('sakemaru')->table('partners')->insert([
            'id' => 10,
            'client_id' => 1,
            'company_id' => 1,
            'bill_group_id' => null,
            'name_main' => 'A社',
        ]);

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

    public function test_publish_normalizes_closing_date_and_zero_partner_id(): void
    {
        DB::connection('sakemaru')->table('external_invoices')->insert([
            'id' => 2,
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'client_id' => 1,
            'partner_id' => 0,
            'closing_bill_id' => 21,
            'closing_date' => '2026-02-28',
            's3_bucket' => 'bucket',
            's3_path' => 'path/b.pdf',
            'file_name' => 'b.pdf',
            'partner_code' => 'P002',
            'partner_name' => 'B社',
            'invoice_number' => 'INV-2',
            'billing_amount' => 6000,
            'metadata' => '{"summary":{"grand_total":6000}}',
            'print_type' => 'INVOICE',
            'creator_id' => 1,
            'is_active' => 1,
            'document_type' => 'invoice',
            'file_type' => 'pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $published = app(DocumentPublishClient::class)->publish('44444444-4444-4444-4444-444444444444');
        $this->assertTrue($published);

        $saved = DB::connection('invoice')->table('documents')->where('uuid', '44444444-4444-4444-4444-444444444444')->first();
        $this->assertNotNull($saved);
        $this->assertSame('2026-02-28', (string) $saved->closing_date);
        $this->assertNull($saved->partner_id);
    }

    public function test_publish_creates_partner_and_partner_user_when_missing(): void
    {
        config([
            'invoice_partner_bootstrap.email_domain' => 'sakemaru.ai',
            'invoice_partner_bootstrap.initial_password' => '12345678',
            'invoice_partner_bootstrap.default_role' => 'admin',
        ]);

        DB::connection('sakemaru')->table('partners')->insert([
            'id' => 5001,
            'client_id' => 6,
            'company_id' => 77,
            'bill_group_id' => 4001,
            'name_main' => 'テスト得意先',
        ]);

        DB::connection('sakemaru')->table('external_invoices')->insert([
            'id' => 3,
            'uuid' => '55555555-5555-5555-5555-555555555555',
            'client_id' => 6,
            'partner_id' => 5001,
            'closing_bill_id' => 31,
            'closing_date' => '2026-02-28',
            's3_bucket' => 'bucket',
            's3_path' => 'path/c.pdf',
            'file_name' => 'c.pdf',
            'partner_code' => 'P5001',
            'partner_name' => 'テスト得意先',
            'invoice_number' => 'INV-3',
            'billing_amount' => 7000,
            'metadata' => '{"summary":{"grand_total":7000}}',
            'print_type' => 'INVOICE',
            'creator_id' => 1,
            'is_active' => 1,
            'document_type' => 'invoice',
            'file_type' => 'pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $published = app(DocumentPublishClient::class)->publish('55555555-5555-5555-5555-555555555555');
        $this->assertTrue($published);

        $partner = DB::connection('invoice')->table('partners')
            ->where('client_id', 6)
            ->where('client_partner_id', 5001)
            ->first();
        $this->assertNotNull($partner);
        $this->assertSame('テスト得意先', $partner->name);
        $this->assertSame(4001, (int) $partner->parent_partner_id);

        $partnerUser = DB::connection('invoice')->table('partner_users')
            ->where('partner_id', $partner->id)
            ->first();
        $this->assertNotNull($partnerUser);
        $this->assertSame('6-5001@sakemaru.ai', $partnerUser->email);
        $this->assertSame('admin', $partnerUser->role);
        $this->assertNotSame('12345678', $partnerUser->password);

        $saved = DB::connection('invoice')->table('documents')
            ->where('uuid', '55555555-5555-5555-5555-555555555555')
            ->first();
        $this->assertNotNull($saved);
        $this->assertSame((int) $partner->id, (int) $saved->partner_id);
    }
}
