<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PartnerPortalCheckpointResetCommand extends Command
{
    protected $signature = 'sync:partner-portal:checkpoint-reset {--client_id=} {--entity_type=}';

    protected $description = 'Reset partner portal sync checkpoints by filter.';

    public function handle(): int
    {
        $scope = (string) config('sync.partner_portal.scope', 'partner_portal');
        $clientId = $this->option('client_id');
        $entityType = $this->option('entity_type');

        $query = DB::connection('mysql')
            ->table('sync_checkpoints')
            ->where('sync_scope', $scope)
            ->when($clientId !== null, fn ($q) => $q->where('client_id', (int) $clientId))
            ->when($entityType !== null, fn ($q) => $q->where('entity_type', (string) $entityType));

        $deleted = $query->delete();

        $this->info("Deleted checkpoints: {$deleted}");

        return self::SUCCESS;
    }
}
