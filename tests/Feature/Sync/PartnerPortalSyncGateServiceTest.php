<?php

namespace Tests\Feature\Sync;

use App\Services\PartnerPortalSyncGateService;
use Illuminate\Support\Facades\DB;
use Tests\Support\SyncDatabaseSetup;
use Tests\TestCase;

class PartnerPortalSyncGateServiceTest extends TestCase
{
    use SyncDatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSyncDatabases();
        config([
            'sync.partner_portal.scope' => 'partner_portal_test',
            'sync.partner_portal.gates.min_runs' => 5,
            'sync.partner_portal.gates.count_diff_ratio_max' => 0.001,
            'sync.partner_portal.gates.error_rate_max' => 0.005,
            'sync.partner_portal.gates.p95_duration_seconds_max' => 300,
        ]);
    }

    public function test_gate_passes_when_recent_runs_match_thresholds(): void
    {
        for ($i = 0; $i < 5; $i++) {
            DB::connection('sakemaru')->table('doc_sync_runs')->insert([
                'sync_scope' => 'partner_portal_test',
                'mode' => 'dry_run',
                'status' => 'success',
                'client_id' => 1,
                'scanned_count' => 1000,
                'upsert_count' => 0,
                'skip_count' => 1000,
                'error_count' => 0,
                'started_at' => sprintf('2026-02-10 10:%02d:00', $i),
                'finished_at' => sprintf('2026-02-10 10:%02d:30', $i),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $result = app(PartnerPortalSyncGateService::class)->evaluate(1);

        $this->assertTrue($result['passed']);
        $this->assertSame('all_checks_passed', $result['reason']);
        $this->assertSame(5, $result['actual_runs']);
        $this->assertTrue($result['checks']['count_diff_ratio']['passed']);
        $this->assertTrue($result['checks']['error_rate']['passed']);
        $this->assertTrue($result['checks']['p95_duration_seconds']['passed']);
        $this->assertTrue($result['checks']['idempotency_same_scanned_count']['passed']);
    }

    public function test_gate_fails_when_not_enough_runs(): void
    {
        for ($i = 0; $i < 2; $i++) {
            DB::connection('sakemaru')->table('doc_sync_runs')->insert([
                'sync_scope' => 'partner_portal_test',
                'mode' => 'dry_run',
                'status' => 'success',
                'client_id' => 1,
                'scanned_count' => 1000,
                'upsert_count' => 0,
                'skip_count' => 1000,
                'error_count' => 0,
                'started_at' => sprintf('2026-02-10 11:%02d:00', $i),
                'finished_at' => sprintf('2026-02-10 11:%02d:30', $i),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $result = app(PartnerPortalSyncGateService::class)->evaluate(1);

        $this->assertFalse($result['passed']);
        $this->assertSame('insufficient_runs', $result['reason']);
        $this->assertSame(5, $result['required_runs']);
        $this->assertSame(2, $result['actual_runs']);
    }
}
