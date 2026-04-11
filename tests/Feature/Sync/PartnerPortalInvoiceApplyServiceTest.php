<?php

namespace Tests\Feature\Sync;

use App\Services\PartnerPortalInvoiceApplyService;
use Illuminate\Support\Facades\DB;
use Tests\Support\SyncDatabaseSetup;
use Tests\TestCase;

class PartnerPortalInvoiceApplyServiceTest extends TestCase
{
    use SyncDatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSyncDatabases();
        config(['sync.partner_portal.scope' => 'partner_portal_test']);
    }

    public function test_apply_inserts_then_updates_invoice_with_checkpoint_resume(): void
    {
        DB::connection('sakemaru')->table('clients')->insert([
            'id' => 1,
            'code' => 'C001',
            'name' => 'Client 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('sakemaru')->table('partners')->insert([
            'id' => 201,
            'client_id' => 1,
            'name' => 'Partner A',
            'is_supplier' => 0,
            'is_active' => 1,
            'updated_at' => '2026-02-10 10:00:00',
        ]);

        DB::connection('sakemaru')->table('buyer_invoices')->insert([
            'id' => 301,
            'uuid' => 'uuid-001',
            'client_id' => 1,
            'partner_id' => 201,
            'closing_bill_id' => 11,
            'closing_balance_overview_id' => 21,
            'closing_balance_price_id' => 31,
            'closing_date' => '2026-01-31',
            's3_bucket' => 'bucket',
            's3_path' => 'path/a.pdf',
            'file_name' => 'a.pdf',
            'file_size' => 1024,
            'file_hash' => 'hash-a',
            'page_count' => 2,
            'partner_code' => 'P001',
            'partner_name' => 'Partner A',
            'invoice_number' => 'INV-001',
            'billing_amount' => 1000,
            'metadata' => '{"k":"v"}',
            'status' => 'published',
            'print_type' => 'pdf',
            'creator_id' => 99,
            'is_active' => 1,
            'created_at' => '2026-02-10 10:00:00',
            'updated_at' => '2026-02-10 10:00:00',
        ]);

        DB::connection('invoice')->table('clients')->insert([
            'id' => 1,
            'name' => 'Client 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('invoice')->table('partners')->insert([
            'id' => 501,
            'client_id' => 1,
            'company_id' => null,
            'client_partner_id' => 201,
            'parent_partner_id' => null,
            'name' => 'Partner A',
            'billing_email' => null,
            'is_active' => 1,
            'can_view_group_invoices' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(PartnerPortalInvoiceApplyService::class);

        $first = $service->run(clientId: 1, limit: 100, fromStart: false);
        $this->assertSame(1, $first['scanned_count']);
        $this->assertSame(1, $first['inserted_count']);
        $this->assertSame(0, $first['updated_count']);
        $this->assertSame(0, $first['error_count']);

        $savedInvoice = DB::connection('invoice')
            ->table('buyer_invoices')
            ->where('uuid', 'uuid-001')
            ->first();
        $this->assertNotNull($savedInvoice);
        $this->assertSame(501, (int) $savedInvoice->partner_id);
        $this->assertSame(1000, (int) $savedInvoice->billing_amount);

        DB::connection('sakemaru')
            ->table('buyer_invoices')
            ->where('id', 301)
            ->update([
                'billing_amount' => 2000,
                'updated_at' => '2026-02-10 11:00:00',
            ]);

        $second = $service->run(clientId: 1, limit: 100, fromStart: false);
        $this->assertSame(1, $second['scanned_count']);
        $this->assertSame(0, $second['inserted_count']);
        $this->assertSame(1, $second['updated_count']);
        $this->assertSame(0, $second['error_count']);

        $updatedInvoice = DB::connection('invoice')
            ->table('buyer_invoices')
            ->where('uuid', 'uuid-001')
            ->first();
        $this->assertNotNull($updatedInvoice);
        $this->assertSame(2000, (int) $updatedInvoice->billing_amount);

        $third = $service->run(clientId: 1, limit: 100, fromStart: false);
        $this->assertSame(0, $third['scanned_count']);
        $this->assertSame(0, $third['error_count']);
    }

    public function test_invoice_apply_stops_when_error_rate_exceeds_threshold(): void
    {
        config([
            'sync.partner_portal.apply.error_rate_stop' => 0.05,
            'sync.partner_portal.apply.max_retries' => 3,
        ]);

        DB::connection('sakemaru')->table('clients')->insert([
            'id' => 1,
            'code' => 'C001',
            'name' => 'Client 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('sakemaru')->table('partners')->insert([
            'id' => 301,
            'client_id' => 1,
            'name' => 'Partner B',
            'is_supplier' => 0,
            'is_active' => 1,
            'updated_at' => '2026-02-10 10:00:00',
        ]);

        DB::connection('sakemaru')->table('buyer_invoices')->insert([
            [
                'id' => 401,
                'uuid' => '',
                'client_id' => 1,
                'partner_id' => 301,
                'closing_bill_id' => 11,
                'closing_balance_overview_id' => 21,
                'closing_balance_price_id' => 31,
                'closing_date' => '2026-01-31',
                's3_bucket' => 'bucket',
                's3_path' => 'path/invalid.pdf',
                'file_name' => 'invalid.pdf',
                'file_size' => 1,
                'file_hash' => null,
                'page_count' => null,
                'partner_code' => 'P301',
                'partner_name' => 'Partner B',
                'invoice_number' => 'INV-BAD',
                'billing_amount' => 100,
                'metadata' => null,
                'status' => 'published',
                'print_type' => 'pdf',
                'creator_id' => 1,
                'is_active' => 1,
                'created_at' => '2026-02-10 10:00:00',
                'updated_at' => '2026-02-10 10:00:00',
            ],
            [
                'id' => 402,
                'uuid' => 'uuid-should-not-run',
                'client_id' => 1,
                'partner_id' => 301,
                'closing_bill_id' => 12,
                'closing_balance_overview_id' => 22,
                'closing_balance_price_id' => 32,
                'closing_date' => '2026-01-31',
                's3_bucket' => 'bucket',
                's3_path' => 'path/skip.pdf',
                'file_name' => 'skip.pdf',
                'file_size' => 2,
                'file_hash' => null,
                'page_count' => null,
                'partner_code' => 'P301',
                'partner_name' => 'Partner B',
                'invoice_number' => 'INV-SKIP',
                'billing_amount' => 200,
                'metadata' => null,
                'status' => 'published',
                'print_type' => 'pdf',
                'creator_id' => 1,
                'is_active' => 1,
                'created_at' => '2026-02-10 10:01:00',
                'updated_at' => '2026-02-10 10:01:00',
            ],
        ]);

        DB::connection('invoice')->table('clients')->insert([
            'id' => 1,
            'name' => 'Client 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('invoice')->table('partners')->insert([
            'id' => 601,
            'client_id' => 1,
            'company_id' => null,
            'client_partner_id' => 301,
            'parent_partner_id' => null,
            'name' => 'Partner B',
            'billing_email' => null,
            'is_active' => 1,
            'can_view_group_invoices' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(PartnerPortalInvoiceApplyService::class)->run(clientId: 1, limit: 10, fromStart: true);

        $this->assertSame(1, $result['scanned_count']);
        $this->assertSame(1, $result['error_count']);
        $this->assertSame(0, $result['inserted_count']);

        $run = DB::connection('mysql')->table('sync_runs')->where('id', $result['run_id'])->first();
        $this->assertNotNull($run);
        $this->assertSame('failed', $run->status);

        $runItem = DB::connection('mysql')->table('sync_run_items')->where('run_id', $result['run_id'])->first();
        $this->assertNotNull($runItem);
        $this->assertSame(1, (int) $runItem->attempt_count);
        $this->assertSame('failed', $runItem->result_status);

        $errorRow = DB::connection('mysql')->table('sync_errors')->where('run_id', $result['run_id'])->first();
        $this->assertNotNull($errorRow);
        $this->assertSame(0, (int) $errorRow->is_retryable);

        $this->assertNull(
            DB::connection('invoice')->table('buyer_invoices')->where('uuid', 'uuid-should-not-run')->first()
        );
    }
}
