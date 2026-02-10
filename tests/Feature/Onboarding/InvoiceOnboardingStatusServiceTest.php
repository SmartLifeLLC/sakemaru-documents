<?php

namespace Tests\Feature\Onboarding;

use App\Models\Partner;
use App\Services\InvoiceOnboardingStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SyncDatabaseSetup;
use Tests\TestCase;

class InvoiceOnboardingStatusServiceTest extends TestCase
{
    use SyncDatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSyncDatabases();
        $this->createOnboardingRequestTable();

        config([
            'invoice_onboarding.enabled' => true,
            'invoice_onboarding.connection' => 'invoice',
            'invoice_onboarding.table' => 'onboarding_requests',
            'invoice_onboarding.source_system' => 'documents',
        ]);
    }

    public function test_latest_for_partner_returns_latest_request(): void
    {
        DB::connection('invoice')->table('onboarding_requests')->insert([
            [
                'id' => 1,
                'source_system' => 'documents',
                'source_client_id' => 1,
                'source_partner_id' => 1001,
                'initial_email' => 'first@example.com',
                'request_dedupe_key' => str_repeat('a', 64),
                'status' => 'requested',
                'requested_at' => '2026-02-11 00:00:00',
                'processed_at' => null,
                'created_at' => '2026-02-11 00:00:00',
                'updated_at' => '2026-02-11 00:00:00',
            ],
            [
                'id' => 2,
                'source_system' => 'documents',
                'source_client_id' => 1,
                'source_partner_id' => 1001,
                'initial_email' => 'second@example.com',
                'request_dedupe_key' => str_repeat('b', 64),
                'status' => 'sent',
                'requested_at' => '2026-02-11 01:00:00',
                'processed_at' => '2026-02-11 01:10:00',
                'created_at' => '2026-02-11 01:00:00',
                'updated_at' => '2026-02-11 01:10:00',
            ],
        ]);

        $partner = $this->makePartner(id: 1001, clientId: 1);
        $latest = app(InvoiceOnboardingStatusService::class)->latestForPartner($partner);

        $this->assertNotNull($latest);
        $this->assertSame(2, $latest['request_id']);
        $this->assertSame('sent', $latest['status']);
        $this->assertSame('second@example.com', $latest['initial_email']);
        $this->assertSame('2026-02-11 01:00:00', $latest['requested_at']);
    }

    public function test_latest_for_partner_returns_null_when_not_found(): void
    {
        $partner = $this->makePartner(id: 9999, clientId: 1);
        $latest = app(InvoiceOnboardingStatusService::class)->latestForPartner($partner);

        $this->assertNull($latest);
    }

    private function createOnboardingRequestTable(): void
    {
        Schema::connection('invoice')->create('onboarding_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('source_system', 40);
            $table->unsignedBigInteger('source_client_id');
            $table->unsignedBigInteger('source_partner_id');
            $table->string('initial_email');
            $table->char('request_dedupe_key', 64);
            $table->string('status', 32);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    private function makePartner(int $id, ?int $clientId): Partner
    {
        $partner = new Partner();
        $partner->setAttribute('id', $id);
        $partner->setAttribute('client_id', $clientId);

        return $partner;
    }
}
