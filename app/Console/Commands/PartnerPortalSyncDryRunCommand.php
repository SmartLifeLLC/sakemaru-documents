<?php

namespace App\Console\Commands;

use App\Services\PartnerPortalSyncDryRunService;
use Illuminate\Console\Command;

class PartnerPortalSyncDryRunCommand extends Command
{
    protected $signature = 'sync:partner-portal:dry-run {--client_id=}';

    protected $description = 'Run partner portal sync dry-run and store aggregate run metrics.';

    public function handle(PartnerPortalSyncDryRunService $service): int
    {
        if (! (bool) config('sync.partner_portal.dry_run_enabled', true)) {
            $this->error('Dry-run is disabled by configuration.');

            return self::FAILURE;
        }

        $clientOption = $this->option('client_id');
        $clientId = $clientOption !== null ? (int) $clientOption : null;

        $result = $service->run($clientId);

        $this->info('Partner portal dry-run completed.');
        $this->table(
            ['run_id', 'client_id', 'partners_count', 'buyers_count', 'invoices_count', 'scanned_count'],
            [[
                $result['run_id'],
                $result['client_id'],
                $result['partners_count'],
                $result['buyers_count'],
                $result['invoices_count'],
                $result['scanned_count'],
            ]]
        );

        return self::SUCCESS;
    }
}
