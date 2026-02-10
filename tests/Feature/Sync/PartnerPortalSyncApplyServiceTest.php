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

        $checkpoint = DB::connection('sakemaru')
            ->table('doc_sync_checkpoints')
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
}
