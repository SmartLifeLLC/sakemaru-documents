<?php

namespace App\Console\Commands;

use App\Services\PartnerPortalSyncApplyService;
use Illuminate\Console\Command;

class PartnerPortalSyncApplyCommand extends Command
{
    protected $signature = 'sync:partner-portal:apply {--client_id=} {--limit=500} {--from_start}';

    protected $description = 'Apply partner sync to invoice DB for a limited client.';

    public function handle(PartnerPortalSyncApplyService $service): int
    {
        $clientOption = $this->option('client_id');

        if ($clientOption === null) {
            $this->error('--client_id is required for limited apply.');

            return self::FAILURE;
        }

        $clientId = (int) $clientOption;
        $limit = max(1, (int) $this->option('limit'));
        $fromStart = (bool) $this->option('from_start');

        $result = $service->run($clientId, $limit, $fromStart);

        $this->info('Partner portal apply completed.');
        $this->table(
            ['run_id', 'client_id', 'scanned_count', 'inserted_count', 'updated_count', 'skipped_count', 'error_count'],
            [[
                $result['run_id'],
                $result['client_id'],
                $result['scanned_count'],
                $result['inserted_count'],
                $result['updated_count'],
                $result['skipped_count'],
                $result['error_count'],
            ]]
        );

        return ((int) $result['error_count']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
