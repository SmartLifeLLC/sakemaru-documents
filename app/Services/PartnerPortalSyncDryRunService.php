<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class PartnerPortalSyncDryRunService
{
    /**
     * @return array<string, int|string|null>
     *
     * @throws Throwable
     */
    public function run(?int $clientId = null): array
    {
        $connection = DB::connection('sakemaru');
        $scope = (string) config('sync.partner_portal.scope', 'partner_portal');

        return $connection->transaction(function () use ($connection, $clientId, $scope): array {
            $now = now();

            $runId = $connection->table('doc_sync_runs')->insertGetId([
                'sync_scope' => $scope,
                'mode' => 'dry_run',
                'status' => 'running',
                'client_id' => $clientId,
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $partnersQuery = $connection->table('partners')->where('is_supplier', 0);

            if ($clientId !== null) {
                $partnersQuery->where('client_id', $clientId);
            }

            $partnersCount = (int) (clone $partnersQuery)->count();

            $buyersCount = (int) $connection->table('buyers as b')
                ->join('partners as p', function ($join): void {
                    $join->on('b.partner_id', '=', 'p.id')
                        ->on('b.client_id', '=', 'p.client_id');
                })
                ->where('p.is_supplier', 0)
                ->when($clientId !== null, fn ($q) => $q->where('p.client_id', $clientId))
                ->count();

            $invoicesCount = (int) $connection->table('buyer_invoices as bi')
                ->join('partners as p', function ($join): void {
                    $join->on('bi.partner_id', '=', 'p.id')
                        ->on('bi.client_id', '=', 'p.client_id');
                })
                ->where('p.is_supplier', 0)
                ->when($clientId !== null, fn ($q) => $q->where('p.client_id', $clientId))
                ->count();

            $scannedCount = $partnersCount + $buyersCount + $invoicesCount;

            $connection->table('doc_sync_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'success',
                    'finished_at' => now(),
                    'scanned_count' => $scannedCount,
                    'upsert_count' => 0,
                    'skip_count' => $scannedCount,
                    'error_count' => 0,
                    'updated_at' => now(),
                ]);

            return [
                'run_id' => $runId,
                'client_id' => $clientId,
                'partners_count' => $partnersCount,
                'buyers_count' => $buyersCount,
                'invoices_count' => $invoicesCount,
                'scanned_count' => $scannedCount,
            ];
        });
    }
}
