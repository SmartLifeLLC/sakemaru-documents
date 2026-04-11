<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CustomInvoiceQueueClient
{
    /**
     * @param  array<string, mixed>  $json
     */
    public function enqueue(array $json, string $requestType, string $documentType, ?string $queueUuid = null): string
    {
        $requestType = trim($requestType);
        if (! in_array($requestType, ['create', 'revise'], true)) {
            throw new InvalidArgumentException('requestType must be create or revise');
        }

        $documentType = trim($documentType);
        if (! in_array($documentType, ['invoice', 'delivery_note', 'rebate_invoice'], true)) {
            throw new InvalidArgumentException('documentType must be invoice, delivery_note, or rebate_invoice');
        }

        $queueUuid = $this->resolveQueueUuid($json, $queueUuid);
        $requestedByUserId = trim((string) data_get($json, 'request_context.requested_by_user_id', ''));
        $payloadJson = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($payloadJson)) {
            throw new RuntimeException('payload_json encoding failed');
        }

        $row = [
            'queue_uuid' => $queueUuid,
            'request_type' => $requestType,
            'document_type' => $documentType,
            'target_client_id' => data_get($json, 'target.client_id'),
            'target_partner_id' => data_get($json, 'target.partner_id'),
            'source_document_uuid' => data_get($json, 'target.source_document_uuid'),
            'payload_json' => $payloadJson,
            'status' => 'queued',
            'requested_by_system' => 'documents',
            'requested_by_user_id' => $requestedByUserId === '' ? null : $requestedByUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            DB::connection($this->writeConnectionName())
                ->table('custom_invoice_queue')
                ->insert($row);
        } catch (QueryException $e) {
            if ($this->isDuplicateEntry($e)) {
                return $queueUuid;
            }

            throw $e;
        }

        return $queueUuid;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStatus(string $queueUuid): ?array
    {
        $queueUuid = trim($queueUuid);
        if ($queueUuid === '') {
            throw new InvalidArgumentException('queueUuid is required');
        }

        $row = DB::connection($this->readConnectionName())
            ->table('custom_invoice_queue')
            ->where('queue_uuid', $queueUuid)
            ->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLatestBySourceDocumentUuid(string $sourceDocumentUuid): ?array
    {
        $sourceDocumentUuid = trim($sourceDocumentUuid);
        if ($sourceDocumentUuid === '') {
            throw new InvalidArgumentException('sourceDocumentUuid is required');
        }

        $row = DB::connection($this->readConnectionName())
            ->table('custom_invoice_queue')
            ->where('source_document_uuid', $sourceDocumentUuid)
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : (array) $row;
    }

    private function readConnectionName(): string
    {
        return is_array(config('database.connections.sakemaru_read'))
            ? 'sakemaru_read'
            : 'sakemaru';
    }

    private function writeConnectionName(): string
    {
        return is_array(config('database.connections.sakemaru_write'))
            ? 'sakemaru_write'
            : 'sakemaru';
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function resolveQueueUuid(array $json, ?string $queueUuid): string
    {
        $queueUuid = trim((string) $queueUuid);
        if (Str::isUuid($queueUuid)) {
            return $queueUuid;
        }

        $requestUuid = trim((string) data_get($json, 'request_context.request_uuid', ''));

        return Str::isUuid($requestUuid) ? $requestUuid : (string) Str::uuid();
    }

    private function isDuplicateEntry(QueryException $e): bool
    {
        $errorCode = (string) ($e->errorInfo[1] ?? '');
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $errorCode === '1062' || $sqlState === '23000';
    }
}
