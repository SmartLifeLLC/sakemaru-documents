<?php

namespace Tests\Feature\Onboarding;

use App\Models\Partner;
use App\Services\InvoiceOnboardingStartService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Support\SyncDatabaseSetup;
use Tests\TestCase;

class InvoiceOnboardingStartServiceTest extends TestCase
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
            'invoice_onboarding.default_status' => 'requested',
        ]);
    }

    public function test_start_upserts_by_source_client_id_and_dedupe_key(): void
    {
        $service = app(InvoiceOnboardingStartService::class);
        $partner = $this->makePartner(id: 501, clientId: 1);

        $first = $service->start($partner, 'TestUser@example.com', 10);
        $second = $service->start($partner, 'testuser@example.com', 11);

        $this->assertNotNull($first['request_id']);
        $this->assertSame($first['dedupe_key'], $second['dedupe_key']);
        $this->assertSame(1, DB::connection('invoice')->table('onboarding_requests')->count());

        $stored = DB::connection('invoice')->table('onboarding_requests')->first();
        $this->assertSame('documents', $stored->source_system);
        $this->assertSame(1, (int) $stored->source_client_id);
        $this->assertSame(501, (int) $stored->source_partner_id);
        $this->assertSame('testuser@example.com', $stored->initial_email);
        $this->assertSame('requested', $stored->status);
        $this->assertSame(11, (int) $stored->requested_by);
    }

    public function test_start_creates_separate_rows_when_source_client_id_differs(): void
    {
        $service = app(InvoiceOnboardingStartService::class);

        $service->start($this->makePartner(id: 700, clientId: 1), 'shared@example.com', 1);
        $service->start($this->makePartner(id: 700, clientId: 2), 'shared@example.com', 2);

        $this->assertSame(2, DB::connection('invoice')->table('onboarding_requests')->count());
    }

    public function test_start_requires_source_client_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source_client_id is required');

        app(InvoiceOnboardingStartService::class)->start($this->makePartner(id: 900, clientId: null), 'a@example.com');
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
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_system', 'source_client_id', 'request_dedupe_key'],
                'onboarding_requests_source_client_dedupe_unique'
            );
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
