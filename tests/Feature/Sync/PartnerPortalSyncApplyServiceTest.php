<?php

namespace Tests\Feature\Sync;

use App\Services\PartnerPortalSyncApplyService;
use Illuminate\Support\Facades\DB;
use Tests\Support\SyncDatabaseSetup;
use Tests\TestCase;

class PartnerPortalSyncApplyServiceTest extends TestCase
{
    use SyncDatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSyncDatabases();
        config(['sync.partner_portal.scope' => 'partner_portal_test']);
    }

    public function test_apply_inserts_then_resumes_from_checkpoint_and_from_start_replays_as_skip(): void
    {
        DB::connection('sakemaru')->table('partners')->insert([
            'id' => 101,
            'client_id' => 1,
            'name' => 'Partner A',
            'is_supplier' => 0,
            'is_active' => 1,
            'updated_at' => '2026-02-10 10:00:00',
        ]);

        DB::connection('invoice')->table('clients')->insert([
            'id' => 1,
            'name' => 'Client 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(PartnerPortalSyncApplyService::class);

        $first = $service->run(clientId: 1, limit: 100, fromStart: false);
        $this->assertSame(1, $first['scanned_count']);
        $this->assertSame(1, $first['inserted_count']);
        $this->assertSame(0, $first['updated_count']);
        $this->assertSame(0, $first['error_count']);

        $targetPartner = DB::connection('invoice')
            ->table('partners')
            ->where('client_id', 1)
            ->where('client_partner_id', 101)
            ->first();
        $this->assertNotNull($targetPartner);
        $this->assertSame('Partner A', $targetPartner->name);

        $checkpoint = DB::connection('mysql')
            ->table('sync_checkpoints')
            ->where('sync_scope', 'partner_portal_test')
            ->where('entity_type', 'partner')
            ->where('client_id', 1)
            ->first();
        $this->assertNotNull($checkpoint);
        $this->assertSame(101, (int) $checkpoint->cursor_id);

        $second = $service->run(clientId: 1, limit: 100, fromStart: false);
        $this->assertSame(0, $second['scanned_count']);
        $this->assertSame(0, $second['inserted_count']);
        $this->assertSame(0, $second['updated_count']);
        $this->assertSame(0, $second['error_count']);

        $third = $service->run(clientId: 1, limit: 100, fromStart: true);
        $this->assertSame(1, $third['scanned_count']);
        $this->assertSame(0, $third['inserted_count']);
        $this->assertSame(0, $third['updated_count']);
        $this->assertSame(1, $third['skipped_count']);
        $this->assertSame(0, $third['error_count']);
    }

    public function test_apply_stops_when_error_rate_threshold_is_exceeded_and_marks_non_retryable_errors(): void
    {
        config([
            'sync.partner_portal.apply.error_rate_stop' => 0.05,
            'sync.partner_portal.apply.max_retries' => 3,
        ]);

        DB::connection('sakemaru')->table('partners')->insert([
            [
                'id' => 201,
                'client_id' => 1,
                'name' => null,
                'is_supplier' => 0,
                'is_active' => 1,
                'updated_at' => '2026-02-10 10:00:00',
            ],
            [
                'id' => 202,
                'client_id' => 1,
                'name' => 'Will Not Be Processed',
                'is_supplier' => 0,
                'is_active' => 1,
                'updated_at' => '2026-02-10 10:01:00',
            ],
        ]);

        DB::connection('invoice')->table('clients')->insert([
            'id' => 1,
            'name' => 'Client 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(PartnerPortalSyncApplyService::class)->run(clientId: 1, limit: 10, fromStart: true);

        $this->assertSame(1, $result['scanned_count']);
        $this->assertSame(0, $result['inserted_count']);
        $this->assertSame(1, $result['error_count']);

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
            DB::connection('invoice')->table('partners')->where('client_partner_id', 202)->first()
        );
    }
}
