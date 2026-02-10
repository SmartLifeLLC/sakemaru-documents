<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class PartnerPortalSyncApplyService
{
    public function __construct(
        private readonly PartnerPortalSyncCheckpointService $checkpointService,
    ) {}

    /**
     * @return array<string, int|string|null>
     *
     * @throws Throwable
     */
    public function run(int $clientId, int $limit = 500, bool $fromStart = false): array
    {
        $source = DB::connection('sakemaru');
        $target = DB::connection('invoice');
        $meta = DB::connection('sakemaru');

        $scope = (string) config('sync.partner_portal.scope', 'partner_portal');

        $checkpointFrom = $fromStart
            ? ['cursor_updated_at' => null, 'cursor_id' => 0, 'last_run_id' => null]
            : $this->checkpointService->get('partner', $clientId);

        $runId = $meta->table('doc_sync_runs')->insertGetId([
            'sync_scope' => $scope,
            'mode' => 'apply',
            'status' => 'running',
            'client_id' => $clientId,
            'checkpoint_from' => json_encode($checkpointFrom, JSON_UNESCAPED_UNICODE),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $scanned = 0;

        try {
            $clientExists = $target->table('clients')->where('id', $clientId)->exists();
            if (! $clientExists) {
                throw new \RuntimeException("Target invoice.clients missing id={$clientId}");
            }

            $rowsQuery = $source->table('partners')
                ->select(['id', 'client_id', 'name', 'is_active', 'updated_at'])
                ->where('client_id', $clientId)
                ->where('is_supplier', 0)
                ->orderBy('updated_at')
                ->orderBy('id');

            $cursorUpdatedAt = $checkpointFrom['cursor_updated_at'];
            $cursorId = (int) $checkpointFrom['cursor_id'];

            if (! $fromStart && $cursorUpdatedAt !== null) {
                $rowsQuery->where(function ($query) use ($cursorUpdatedAt, $cursorId): void {
                    $query->where('updated_at', '>', $cursorUpdatedAt)
                        ->orWhere(function ($q) use ($cursorUpdatedAt, $cursorId): void {
                            $q->where('updated_at', '=', $cursorUpdatedAt)
                                ->where('id', '>', $cursorId);
                        });
                });
            }

            $rows = $rowsQuery->limit($limit)->get();

            $scanned = $rows->count();

            if ($scanned === 0) {
                $this->finishRun(
                    $meta,
                    $runId,
                    'success',
                    $scanned,
                    0,
                    0,
                    0,
                    [
                        'cursor_updated_at' => $cursorUpdatedAt,
                        'cursor_id' => $cursorId,
                        'last_run_id' => $runId,
                    ]
                );

                return [
                    'run_id' => $runId,
                    'client_id' => $clientId,
                    'scanned_count' => 0,
                    'inserted_count' => 0,
                    'updated_count' => 0,
                    'skipped_count' => 0,
                    'error_count' => 0,
                ];
            }

            $sourceIds = $rows->pluck('id')->all();

            $existing = $target->table('partners')
                ->select(['client_partner_id', 'name', 'is_active'])
                ->where('client_id', $clientId)
                ->whereIn('client_partner_id', $sourceIds)
                ->get()
                ->keyBy(fn ($r) => (string) $r->client_partner_id);

            $runItems = [];
            $errorRows = [];
            $mappingRows = [];

            foreach ($rows as $row) {
                $now = now();
                $sourcePk = (string) $row->id;
                $payload = [
                    'client_id' => (int) $row->client_id,
                    'client_partner_id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                    'is_active' => (bool) $row->is_active,
                ];

                $idem = hash('sha256', 'partner|'.$payload['client_id'].'|'.$payload['client_partner_id'].'|apply');
                $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

                try {
                    if (($payload['name'] ?? '') === '') {
                        throw new \RuntimeException('partner name is empty');
                    }

                    $current = $existing->get($sourcePk);

                    if ($current === null) {
                        $target->table('partners')->insert([
                            'client_id' => $payload['client_id'],
                            'company_id' => null,
                            'client_partner_id' => $payload['client_partner_id'],
                            'parent_partner_id' => null,
                            'name' => $payload['name'],
                            'billing_email' => null,
                            'is_active' => $payload['is_active'] ? 1 : 0,
                            'can_view_group_invoices' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $inserted++;
                        $operation = 'insert';
                        $resultStatus = 'success';
                    } else {
                        $needsUpdate = ((string) $current->name !== $payload['name'])
                            || ((int) $current->is_active !== ($payload['is_active'] ? 1 : 0));

                        if ($needsUpdate) {
                            $target->table('partners')
                                ->where('client_id', $payload['client_id'])
                                ->where('client_partner_id', $payload['client_partner_id'])
                                ->update([
                                    'name' => $payload['name'],
                                    'is_active' => $payload['is_active'] ? 1 : 0,
                                    'updated_at' => $now,
                                ]);
                            $updated++;
                            $operation = 'update';
                            $resultStatus = 'success';
                        } else {
                            $skipped++;
                            $operation = 'skip';
                            $resultStatus = 'skipped';
                        }
                    }

                    $runItems[] = [
                        'run_id' => $runId,
                        'entity_type' => 'partner',
                        'source_client_id' => $payload['client_id'],
                        'source_pk' => $sourcePk,
                        'operation' => $operation,
                        'idempotency_key' => $idem,
                        'payload_hash' => $payloadHash,
                        'result_status' => $resultStatus,
                        'attempt_count' => 1,
                        'processed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $mappingRows[] = [
                        'entity_type' => 'partner',
                        'source_client_id' => (int) $row->client_id,
                        'source_id' => $sourcePk,
                        'source_code' => null,
                        'target_system' => 'invoice',
                        'target_id' => (string) $payload['client_partner_id'],
                        'mapping_status' => 'active',
                        'confidence' => 1.00,
                        'first_synced_at' => $now,
                        'last_synced_at' => $now,
                        'stale_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                } catch (Throwable $e) {
                    $errors++;

                    $runItems[] = [
                        'run_id' => $runId,
                        'entity_type' => 'partner',
                        'source_client_id' => (int) $row->client_id,
                        'source_pk' => $sourcePk,
                        'operation' => 'skip',
                        'idempotency_key' => $idem,
                        'payload_hash' => $payloadHash,
                        'result_status' => 'failed',
                        'attempt_count' => 1,
                        'processed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $errorRows[] = [
                        'run_id' => $runId,
                        'run_item_id' => null,
                        'entity_type' => 'partner',
                        'source_client_id' => (int) $row->client_id,
                        'source_pk' => $sourcePk,
                        'stage' => 'apply',
                        'error_code' => 'apply_partner_failed',
                        'error_message' => mb_substr($e->getMessage(), 0, 1000),
                        'error_context' => json_encode(['exception' => get_class($e)], JSON_UNESCAPED_UNICODE),
                        'is_retryable' => true,
                        'first_seen_at' => $now,
                        'last_seen_at' => $now,
                        'resolved_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($runItems !== []) {
                foreach (array_chunk($runItems, 300) as $chunk) {
                    $meta->table('doc_sync_run_items')->insert($chunk);
                }
            }

            if ($errorRows !== []) {
                foreach (array_chunk($errorRows, 300) as $chunk) {
                    $meta->table('doc_sync_errors')->insert($chunk);
                }
            }

            if ($mappingRows !== []) {
                foreach (array_chunk($mappingRows, 300) as $chunk) {
                    $meta->table('doc_sync_mappings')->upsert(
                        $chunk,
                        ['entity_type', 'source_client_id', 'source_id', 'target_system'],
                        ['target_id', 'mapping_status', 'confidence', 'last_synced_at', 'stale_at', 'updated_at']
                    );
                }
            }

            $status = $errors > 0 ? 'partial' : 'success';
            $upsert = $inserted + $updated;

            $lastRow = $rows->last();
            $checkpointTo = [
                'cursor_updated_at' => $lastRow?->updated_at !== null ? (string) $lastRow->updated_at : $cursorUpdatedAt,
                'cursor_id' => $lastRow !== null ? (int) $lastRow->id : $cursorId,
                'last_run_id' => $runId,
            ];

            if ($errors === 0) {
                $this->checkpointService->put(
                    'partner',
                    $clientId,
                    $checkpointTo['cursor_updated_at'],
                    (int) $checkpointTo['cursor_id'],
                    $runId,
                );
            }

            $this->finishRun($meta, $runId, $status, $scanned, $upsert, $skipped, $errors, $checkpointTo);

            return [
                'run_id' => $runId,
                'client_id' => $clientId,
                'scanned_count' => $scanned,
                'inserted_count' => $inserted,
                'updated_count' => $updated,
                'skipped_count' => $skipped,
                'error_count' => $errors,
            ];
        } catch (Throwable $e) {
            $this->finishRun(
                $meta,
                $runId,
                'failed',
                $scanned,
                $inserted + $updated,
                $skipped,
                $errors + 1,
                [
                    'cursor_updated_at' => $checkpointFrom['cursor_updated_at'],
                    'cursor_id' => (int) $checkpointFrom['cursor_id'],
                    'last_run_id' => $checkpointFrom['last_run_id'],
                ]
            );

            $meta->table('doc_sync_errors')->insert([
                'run_id' => $runId,
                'run_item_id' => null,
                'entity_type' => 'partner',
                'source_client_id' => $clientId,
                'source_pk' => '0',
                'stage' => 'bootstrap',
                'error_code' => 'apply_bootstrap_failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'error_context' => json_encode(['exception' => get_class($e)], JSON_UNESCAPED_UNICODE),
                'is_retryable' => false,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'resolved_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array{cursor_updated_at:?string,cursor_id:int,last_run_id:?int} $checkpointTo
     */
    private function finishRun($meta, int $runId, string $status, int $scanned, int $upsert, int $skip, int $error, array $checkpointTo): void
    {
        $meta->table('doc_sync_runs')
            ->where('id', $runId)
            ->update([
                'status' => $status,
                'finished_at' => now(),
                'checkpoint_to' => json_encode($checkpointTo, JSON_UNESCAPED_UNICODE),
                'scanned_count' => $scanned,
                'upsert_count' => $upsert,
                'skip_count' => $skip,
                'error_count' => $error,
                'updated_at' => now(),
            ]);
    }
}
