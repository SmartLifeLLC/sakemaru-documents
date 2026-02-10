<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class PartnerPortalInvoiceApplyService
{
    public function __construct(
        private readonly PartnerPortalSyncCheckpointService $checkpointService,
    ) {}

    /**
     * @return array<string, int|string|null>
     *
     * @throws Throwable
     */
    public function run(int $clientId, int $limit = 1000, bool $fromStart = false): array
    {
        $source = DB::connection('sakemaru');
        $target = DB::connection('invoice');
        $meta = DB::connection('sakemaru');

        $scope = (string) config('sync.partner_portal.scope', 'partner_portal');
        $maxRetries = max(1, (int) config('sync.partner_portal.apply.max_retries', 3));
        $errorRateStop = max(0.0, (float) config('sync.partner_portal.apply.error_rate_stop', 0.05));
        $retryDelayMs = max(0, (int) config('sync.partner_portal.apply.retry_delay_ms', 50));

        $checkpointFrom = $fromStart
            ? ['cursor_updated_at' => null, 'cursor_id' => 0, 'last_run_id' => null]
            : $this->checkpointService->get('buyer_invoice', $clientId);

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
            $client = $source->table('clients')->where('id', $clientId)->first(['id', 'code', 'name']);
            if (! $client) {
                throw new \RuntimeException("Source client not found: id={$clientId}");
            }

            $targetClientExists = $target->table('clients')->where('id', $clientId)->exists();
            if (! $targetClientExists) {
                throw new \RuntimeException("Target invoice.clients missing id={$clientId}");
            }

            $rowsQuery = $source->table('buyer_invoices as bi')
                ->join('partners as p', function ($join): void {
                    $join->on('bi.partner_id', '=', 'p.id')
                        ->on('bi.client_id', '=', 'p.client_id');
                })
                ->where('bi.client_id', $clientId)
                ->where('p.is_supplier', 0)
                ->orderBy('bi.updated_at')
                ->orderBy('bi.id');

            $cursorUpdatedAt = $checkpointFrom['cursor_updated_at'];
            $cursorId = (int) $checkpointFrom['cursor_id'];

            if (! $fromStart && $cursorUpdatedAt !== null) {
                $rowsQuery->where(function ($query) use ($cursorUpdatedAt, $cursorId): void {
                    $query->where('bi.updated_at', '>', $cursorUpdatedAt)
                        ->orWhere(function ($q) use ($cursorUpdatedAt, $cursorId): void {
                            $q->where('bi.updated_at', '=', $cursorUpdatedAt)
                                ->where('bi.id', '>', $cursorId);
                        });
                });
            }

            $rows = $rowsQuery->limit($limit)->get([
                'bi.id',
                'bi.uuid',
                'bi.client_id',
                'bi.partner_id',
                'bi.closing_bill_id',
                'bi.closing_balance_overview_id',
                'bi.closing_balance_price_id',
                'bi.closing_date',
                'bi.s3_bucket',
                'bi.s3_path',
                'bi.file_name',
                'bi.file_size',
                'bi.file_hash',
                'bi.page_count',
                'bi.partner_code',
                'bi.partner_name',
                'bi.invoice_number',
                'bi.billing_amount',
                'bi.metadata',
                'bi.status',
                'bi.print_type',
                'bi.creator_id',
                'bi.is_active',
                'bi.created_at',
                'bi.updated_at',
            ]);

            if ($rows->isEmpty()) {
                $this->finishRun(
                    $meta,
                    $runId,
                    'success',
                    0,
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

            $uuids = $rows->pluck('uuid')->all();
            $existingRows = $target->table('buyer_invoices')
                ->whereIn('uuid', $uuids)
                ->get([
                    'uuid',
                    'client_id',
                    'partner_id',
                    'closing_bill_id',
                    'closing_balance_overview_id',
                    'closing_balance_price_id',
                    'closing_date',
                    's3_bucket',
                    's3_path',
                    'file_name',
                    'file_size',
                    'file_hash',
                    'page_count',
                    'client_code',
                    'client_name',
                    'partner_code',
                    'partner_name',
                    'invoice_number',
                    'billing_amount',
                    'metadata',
                    'status',
                    'print_type',
                    'creator_id',
                    'is_active',
                    'created_at',
                    'updated_at',
                ])
                ->keyBy(fn ($existingRow) => (string) $existingRow->uuid);

            $partnerIds = $rows->pluck('partner_id')->unique()->values()->all();
            $partnerMap = $target->table('partners')
                ->where('client_id', $clientId)
                ->whereIn('client_partner_id', $partnerIds)
                ->pluck('id', 'client_partner_id')
                ->map(fn ($value) => (int) $value)
                ->all();

            $runItems = [];
            $errorRows = [];
            $mappingRows = [];
            $lastProcessedRow = null;
            $stoppedByErrorRate = false;

            foreach ($rows as $row) {
                $lastProcessedRow = $row;
                $scanned++;

                $sourcePk = (string) $row->id;
                $targetPartnerId = $partnerMap[(int) $row->partner_id] ?? null;

                $payload = [
                    'uuid' => (string) $row->uuid,
                    'client_id' => (int) $row->client_id,
                    'partner_id' => $targetPartnerId,
                    'closing_bill_id' => (int) $row->closing_bill_id,
                    'closing_balance_overview_id' => (int) $row->closing_balance_overview_id,
                    'closing_balance_price_id' => (int) $row->closing_balance_price_id,
                    'closing_date' => (string) $row->closing_date,
                    's3_bucket' => (string) $row->s3_bucket,
                    's3_path' => (string) $row->s3_path,
                    'file_name' => (string) $row->file_name,
                    'file_size' => $row->file_size !== null ? (int) $row->file_size : null,
                    'file_hash' => $row->file_hash !== null ? (string) $row->file_hash : null,
                    'page_count' => $row->page_count !== null ? (int) $row->page_count : null,
                    'client_code' => (string) $client->code,
                    'client_name' => (string) $client->name,
                    'partner_code' => $row->partner_code !== null ? (string) $row->partner_code : null,
                    'partner_name' => $row->partner_name !== null ? (string) $row->partner_name : null,
                    'invoice_number' => $row->invoice_number !== null ? (string) $row->invoice_number : null,
                    'billing_amount' => (int) $row->billing_amount,
                    'metadata' => $row->metadata,
                    'status' => (string) $row->status,
                    'print_type' => (string) $row->print_type,
                    'creator_id' => (int) $row->creator_id,
                    'is_active' => (int) $row->is_active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];

                $idem = hash('sha256', 'buyer_invoice|'.$payload['client_id'].'|'.$payload['uuid'].'|apply');
                $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
                $attempt = 0;

                while ($attempt < $maxRetries) {
                    $attempt++;
                    $now = now();

                    try {
                        if ($payload['uuid'] === '') {
                            throw new InvalidArgumentException('buyer_invoice uuid is empty');
                        }

                        $current = $existingRows->get($payload['uuid']);
                        $isExisting = $current !== null;

                        if (! $isExisting) {
                            $target->table('buyer_invoices')->insert($payload);
                            $inserted++;
                            $operation = 'insert';
                        } else {
                            $currentPayload = [
                                'uuid' => (string) $current->uuid,
                                'client_id' => (int) $current->client_id,
                                'partner_id' => $current->partner_id !== null ? (int) $current->partner_id : null,
                                'closing_bill_id' => (int) $current->closing_bill_id,
                                'closing_balance_overview_id' => (int) $current->closing_balance_overview_id,
                                'closing_balance_price_id' => (int) $current->closing_balance_price_id,
                                'closing_date' => (string) $current->closing_date,
                                's3_bucket' => (string) $current->s3_bucket,
                                's3_path' => (string) $current->s3_path,
                                'file_name' => (string) $current->file_name,
                                'file_size' => $current->file_size !== null ? (int) $current->file_size : null,
                                'file_hash' => $current->file_hash !== null ? (string) $current->file_hash : null,
                                'page_count' => $current->page_count !== null ? (int) $current->page_count : null,
                                'client_code' => (string) $current->client_code,
                                'client_name' => (string) $current->client_name,
                                'partner_code' => $current->partner_code !== null ? (string) $current->partner_code : null,
                                'partner_name' => $current->partner_name !== null ? (string) $current->partner_name : null,
                                'invoice_number' => $current->invoice_number !== null ? (string) $current->invoice_number : null,
                                'billing_amount' => (int) $current->billing_amount,
                                'metadata' => $current->metadata,
                                'status' => (string) $current->status,
                                'print_type' => (string) $current->print_type,
                                'creator_id' => (int) $current->creator_id,
                                'is_active' => (int) $current->is_active,
                                'created_at' => $current->created_at,
                                'updated_at' => $current->updated_at,
                            ];

                            $currentHash = hash('sha256', json_encode($currentPayload, JSON_UNESCAPED_UNICODE));

                            if ($currentHash === $payloadHash) {
                                $skipped++;
                                $operation = 'skip';
                            } else {
                                $target->table('buyer_invoices')
                                    ->where('uuid', $payload['uuid'])
                                    ->update($payload);
                                $updated++;
                                $operation = 'update';
                            }
                        }

                        $existingRows->put($payload['uuid'], (object) [
                            'uuid' => $payload['uuid'],
                            'client_id' => $payload['client_id'],
                            'partner_id' => $payload['partner_id'],
                            'closing_bill_id' => $payload['closing_bill_id'],
                            'closing_balance_overview_id' => $payload['closing_balance_overview_id'],
                            'closing_balance_price_id' => $payload['closing_balance_price_id'],
                            'closing_date' => $payload['closing_date'],
                            's3_bucket' => $payload['s3_bucket'],
                            's3_path' => $payload['s3_path'],
                            'file_name' => $payload['file_name'],
                            'file_size' => $payload['file_size'],
                            'file_hash' => $payload['file_hash'],
                            'page_count' => $payload['page_count'],
                            'client_code' => $payload['client_code'],
                            'client_name' => $payload['client_name'],
                            'partner_code' => $payload['partner_code'],
                            'partner_name' => $payload['partner_name'],
                            'invoice_number' => $payload['invoice_number'],
                            'billing_amount' => $payload['billing_amount'],
                            'metadata' => $payload['metadata'],
                            'status' => $payload['status'],
                            'print_type' => $payload['print_type'],
                            'creator_id' => $payload['creator_id'],
                            'is_active' => $payload['is_active'],
                            'created_at' => $payload['created_at'],
                            'updated_at' => $payload['updated_at'],
                        ]);

                        $runItems[] = [
                            'run_id' => $runId,
                            'entity_type' => 'buyer_invoice',
                            'source_client_id' => (int) $row->client_id,
                            'source_pk' => $sourcePk,
                            'operation' => $operation,
                            'idempotency_key' => $idem,
                            'payload_hash' => $payloadHash,
                            'result_status' => $operation === 'skip' ? 'skipped' : 'success',
                            'attempt_count' => $attempt,
                            'processed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $mappingRows[] = [
                            'entity_type' => 'buyer_invoice',
                            'source_client_id' => (int) $row->client_id,
                            'source_id' => $sourcePk,
                            'source_code' => (string) $row->uuid,
                            'target_system' => 'invoice',
                            'target_id' => (string) $payload['uuid'],
                            'mapping_status' => 'active',
                            'confidence' => 1.00,
                            'first_synced_at' => $now,
                            'last_synced_at' => $now,
                            'stale_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        break;
                    } catch (Throwable $e) {
                        $isRetryable = $this->isRetryableException($e);

                        if ($isRetryable && $attempt < $maxRetries) {
                            if ($retryDelayMs > 0) {
                                usleep($retryDelayMs * 1000);
                            }

                            continue;
                        }

                        $errors++;

                        $runItems[] = [
                            'run_id' => $runId,
                            'entity_type' => 'buyer_invoice',
                            'source_client_id' => (int) $row->client_id,
                            'source_pk' => $sourcePk,
                            'operation' => 'skip',
                            'idempotency_key' => $idem,
                            'payload_hash' => $payloadHash,
                            'result_status' => 'failed',
                            'attempt_count' => $attempt,
                            'processed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $errorRows[] = [
                            'run_id' => $runId,
                            'run_item_id' => null,
                            'entity_type' => 'buyer_invoice',
                            'source_client_id' => (int) $row->client_id,
                            'source_pk' => $sourcePk,
                            'stage' => 'apply',
                            'error_code' => 'apply_buyer_invoice_failed',
                            'error_message' => mb_substr($e->getMessage(), 0, 1000),
                            'error_context' => json_encode(['exception' => get_class($e)], JSON_UNESCAPED_UNICODE),
                            'is_retryable' => $isRetryable,
                            'first_seen_at' => $now,
                            'last_seen_at' => $now,
                            'resolved_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        break;
                    }
                }

                if ($scanned > 0 && ($errors / $scanned) > $errorRateStop) {
                    $stoppedByErrorRate = true;
                    break;
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
                        ['source_code', 'target_id', 'mapping_status', 'confidence', 'last_synced_at', 'stale_at', 'updated_at']
                    );
                }
            }

            $status = $stoppedByErrorRate ? 'failed' : ($errors > 0 ? 'partial' : 'success');
            $upsert = $inserted + $updated;
            $checkpointTo = [
                'cursor_updated_at' => $lastProcessedRow?->updated_at !== null ? (string) $lastProcessedRow->updated_at : $cursorUpdatedAt,
                'cursor_id' => $lastProcessedRow !== null ? (int) $lastProcessedRow->id : $cursorId,
                'last_run_id' => $runId,
            ];

            if ($errors === 0) {
                $this->checkpointService->put(
                    'buyer_invoice',
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
                'entity_type' => 'buyer_invoice',
                'source_client_id' => $clientId,
                'source_pk' => '0',
                'stage' => 'bootstrap',
                'error_code' => 'apply_buyer_invoice_bootstrap_failed',
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

    private function isRetryableException(Throwable $e): bool
    {
        return ! $e instanceof InvalidArgumentException;
    }
}
