<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PartnerPortalCheckpointShowCommand extends Command
{
    protected $signature = 'sync:partner-portal:checkpoint-show {--client_id=} {--entity_type=}';

    protected $description = 'Show partner portal sync checkpoints.';

    public function handle(): int
    {
        $scope = (string) config('sync.partner_portal.scope', 'partner_portal');
        $clientId = $this->option('client_id');
        $entityType = $this->option('entity_type');

        $query = DB::connection('sakemaru')
            ->table('doc_sync_checkpoints')
            ->where('sync_scope', $scope)
            ->when($clientId !== null, fn ($q) => $q->where('client_id', (int) $clientId))
            ->when($entityType !== null, fn ($q) => $q->where('entity_type', (string) $entityType))
            ->orderBy('entity_type')
            ->orderBy('client_id');

        $rows = $query->get([
            'entity_type',
            'client_id',
            'cursor_updated_at',
            'cursor_id',
            'last_run_id',
            'updated_at',
        ]);

        if ($rows->isEmpty()) {
            $this->warn('No checkpoints found.');

            return self::SUCCESS;
        }

        $this->table(
            ['entity_type', 'client_id', 'cursor_updated_at', 'cursor_id', 'last_run_id', 'updated_at'],
            $rows->map(fn ($row) => [
                $row->entity_type,
                $row->client_id,
                $row->cursor_updated_at,
                $row->cursor_id,
                $row->last_run_id,
                $row->updated_at,
            ])->all()
        );

        return self::SUCCESS;
    }
}
