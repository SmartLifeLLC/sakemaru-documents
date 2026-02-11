<?php

namespace App\Services;

use App\Models\ExternalInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class DocumentPublishClient
{
    /**
     * @return array{table: string, payload: array<string, mixed>}
     */
    public function buildPreview(string $sourceUuid): array
    {
        $source = $this->findSource($sourceUuid);
        $table = $this->resolveInvoiceDocumentsTable();
        $payload = $this->buildUpsertPayload($table, $source);

        return [
            'table' => $table,
            'payload' => $payload,
        ];
    }

    public function publish(string $sourceUuid): bool
    {
        $source = $this->findSource($sourceUuid);
        $table = $this->resolveInvoiceDocumentsTable();
        $payload = $this->buildUpsertPayload($table, $source);

        return DB::connection($this->writeConnectionName())
            ->table($table)
            ->updateOrInsert(
                ['uuid' => $source['uuid']],
                $payload,
            );
    }

    public function unpublish(string $sourceUuid): bool
    {
        $sourceUuid = trim($sourceUuid);
        if ($sourceUuid === '') {
            throw new InvalidArgumentException('sourceUuid is required');
        }

        $table = $this->resolveInvoiceDocumentsTable();
        $columns = array_flip(Schema::connection($this->writeConnectionName())->getColumnListing($table));
        $payload = ['updated_at' => now()];

        if (isset($columns['is_active'])) {
            $payload['is_active'] = 0;
        }

        if (isset($columns['status'])) {
            $payload['status'] = 'canceled';
        }

        $updated = DB::connection($this->writeConnectionName())
            ->table($table)
            ->where('uuid', $sourceUuid)
            ->update($payload);

        return $updated > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function findSource(string $sourceUuid): array
    {
        $sourceUuid = trim($sourceUuid);
        if ($sourceUuid === '') {
            throw new InvalidArgumentException('sourceUuid is required');
        }

        $source = ExternalInvoice::on($this->readConnectionName())
            ->where('uuid', $sourceUuid)
            ->first();

        if ($source === null) {
            throw new RuntimeException("external_invoices not found: {$sourceUuid}");
        }

        return $source->toArray();
    }

    private function resolveInvoiceDocumentsTable(): string
    {
        $schema = Schema::connection($this->writeConnectionName());
        if ($schema->hasTable('documents')) {
            return 'documents';
        }

        if ($schema->hasTable('buyer_invoices')) {
            return 'buyer_invoices';
        }

        throw new RuntimeException('invoice documents table was not found.');
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function buildUpsertPayload(string $table, array $source): array
    {
        $columns = array_flip(Schema::connection($this->writeConnectionName())->getColumnListing($table));
        $base = [
            'client_id' => $source['client_id'] ?? null,
            'partner_id' => $source['partner_id'] ?? null,
            'closing_bill_id' => $source['closing_bill_id'] ?? null,
            'closing_balance_overview_id' => $source['closing_balance_overview_id'] ?? null,
            'closing_balance_price_id' => $source['closing_balance_price_id'] ?? null,
            'closing_date' => $source['closing_date'] ?? null,
            's3_bucket' => $source['s3_bucket'] ?? null,
            's3_path' => $source['s3_path'] ?? null,
            'file_name' => $source['file_name'] ?? null,
            'file_size' => $source['file_size'] ?? null,
            'file_hash' => $source['file_hash'] ?? null,
            'page_count' => $source['page_count'] ?? null,
            'partner_code' => $source['partner_code'] ?? null,
            'partner_name' => $source['partner_name'] ?? null,
            'invoice_number' => $source['invoice_number'] ?? null,
            'billing_amount' => $source['billing_amount'] ?? null,
            'metadata' => isset($source['metadata']) ? json_encode($source['metadata'], JSON_UNESCAPED_UNICODE) : null,
            'status' => 'synced',
            'print_type' => $source['print_type'] ?? null,
            'creator_id' => $source['creator_id'] ?? null,
            'is_active' => 1,
            'document_type' => $source['document_type'] ?? null,
            'file_type' => $source['file_type'] ?? null,
            'updated_at' => now(),
            'created_at' => now(),
        ];

        $payload = [];
        foreach ($base as $column => $value) {
            if (isset($columns[$column])) {
                $payload[$column] = $value;
            }
        }

        return $payload;
    }

    private function readConnectionName(): string
    {
        return is_array(config('database.connections.sakemaru_read'))
            ? 'sakemaru_read'
            : 'sakemaru';
    }

    private function writeConnectionName(): string
    {
        return is_array(config('database.connections.invoice_write'))
            ? 'invoice_write'
            : 'invoice';
    }
}
