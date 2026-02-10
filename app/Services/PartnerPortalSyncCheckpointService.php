<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PartnerPortalSyncCheckpointService
{
    /**
     * @return array{cursor_updated_at: ?string, cursor_id: int, last_run_id: ?int}
     */
    public function get(string $entityType, int $clientId): array
    {
        $scope = (string) config('sync.partner_portal.scope', 'partner_portal');

        $row = DB::connection('sakemaru')
            ->table('doc_sync_checkpoints')
            ->where('sync_scope', $scope)
            ->where('entity_type', $entityType)
            ->where('client_id', $clientId)
            ->first();

        if ($row === null) {
            return [
                'cursor_updated_at' => null,
                'cursor_id' => 0,
                'last_run_id' => null,
            ];
        }

        return [
            'cursor_updated_at' => $row->cursor_updated_at !== null ? (string) $row->cursor_updated_at : null,
            'cursor_id' => (int) $row->cursor_id,
            'last_run_id' => $row->last_run_id !== null ? (int) $row->last_run_id : null,
        ];
    }

    public function put(string $entityType, int $clientId, ?string $cursorUpdatedAt, int $cursorId, int $runId): void
    {
        $scope = (string) config('sync.partner_portal.scope', 'partner_portal');
        $now = now();
        DB::connection('sakemaru')
            ->table('doc_sync_checkpoints')
            ->upsert(
                [[
                    'sync_scope' => $scope,
                    'entity_type' => $entityType,
                    'client_id' => $clientId,
                    'cursor_updated_at' => $cursorUpdatedAt,
                    'cursor_id' => $cursorId,
                    'last_run_id' => $runId,
                    'lock_version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['sync_scope', 'entity_type', 'client_id'],
                ['cursor_updated_at', 'cursor_id', 'last_run_id', 'updated_at']
            );
    }
}
