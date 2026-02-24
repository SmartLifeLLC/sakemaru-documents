<?php

namespace App\Services;

use App\Models\ExternalInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $this->ensureTargetPartnerAndUser($source);
        $table = $this->resolveInvoiceDocumentsTable();
        $payload = $this->buildUpsertPayload($table, $source);

        return DB::connection($this->writeConnectionName())
            ->table($table)
            ->updateOrInsert(
                ['uuid' => $source['uuid']],
                $payload,
            );
    }

    public function isPublished(string $sourceUuid): bool
    {
        $sourceUuid = trim($sourceUuid);
        if ($sourceUuid === '') {
            throw new InvalidArgumentException('sourceUuid is required');
        }

        $table = $this->resolveInvoiceDocumentsTable();
        $query = DB::connection($this->writeConnectionName())
            ->table($table)
            ->where('uuid', $sourceUuid);

        $columns = array_flip(Schema::connection($this->writeConnectionName())->getColumnListing($table));

        if (isset($columns['is_active'])) {
            $query->where('is_active', 1);
        }

        if (isset($columns['status'])) {
            $query->where('status', '!=', 'canceled');
        }

        return $query->exists();
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
        $clientContext = $this->resolveClientContext($source);
        $targetPartnerId = $this->resolveTargetPartnerId(
            isset($source['client_id']) ? (int) $source['client_id'] : null,
            $source['partner_id'] ?? null,
        );
        $base = [
            'client_id' => $source['client_id'] ?? null,
            'client_code' => $clientContext['code'],
            'client_name' => $clientContext['name'],
            'partner_id' => $targetPartnerId,
            'closing_bill_id' => $source['closing_bill_id'] ?? null,
            'closing_balance_overview_id' => $source['closing_balance_overview_id'] ?? null,
            'closing_balance_price_id' => $source['closing_balance_price_id'] ?? null,
            'closing_date' => $this->normalizeClosingDate($source['closing_date'] ?? null),
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

    /**
     * @param  array<string, mixed>  $source
     * @return array{code: ?string, name: ?string}
     */
    private function resolveClientContext(array $source): array
    {
        $code = $source['client_code'] ?? null;
        $name = $source['client_name'] ?? null;

        $clientId = isset($source['client_id']) ? (int) $source['client_id'] : 0;
        if (($code === null || $name === null)
            && $clientId > 0
            && Schema::connection($this->readConnectionName())->hasTable('clients')) {
            $client = DB::connection($this->readConnectionName())
                ->table('clients')
                ->where('id', $clientId)
                ->first(['code', 'name']);

            if ($client !== null) {
                $code ??= isset($client->code) ? (string) $client->code : null;
                $name ??= isset($client->name) ? (string) $client->name : null;
            }
        }

        return [
            'code' => $code !== null ? (string) $code : null,
            'name' => $name !== null ? (string) $name : null,
        ];
    }

    private function normalizePartnerId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $partnerId = (int) $value;

        return $partnerId > 0 ? $partnerId : null;
    }

    private function resolveTargetPartnerId(?int $clientId, mixed $sourcePartnerId): ?int
    {
        $partnerId = $this->normalizePartnerId($sourcePartnerId);
        if ($partnerId === null || $clientId === null || $clientId <= 0) {
            return null;
        }

        if (! Schema::connection($this->writeConnectionName())->hasTable('partners')) {
            return null;
        }

        $partner = DB::connection($this->writeConnectionName())
            ->table('partners')
            ->where('client_id', $clientId)
            ->where(function ($query) use ($partnerId): void {
                $query->where('id', $partnerId)
                    ->orWhere('client_partner_id', $partnerId);
            })
            ->first(['id']);

        return $partner !== null ? (int) $partner->id : null;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function ensureTargetPartnerAndUser(array $source): void
    {
        $clientId = isset($source['client_id']) ? (int) $source['client_id'] : null;
        $sourcePartnerId = $this->normalizePartnerId($source['partner_id'] ?? null);

        if ($clientId === null || $clientId <= 0 || $sourcePartnerId === null) {
            return;
        }

        if ($this->resolveTargetPartnerId($clientId, $sourcePartnerId) !== null) {
            return;
        }

        $invoiceSchema = Schema::connection($this->writeConnectionName());
        if (! $invoiceSchema->hasTable('partners')) {
            throw new RuntimeException('invoice partners table was not found.');
        }
        if (! $invoiceSchema->hasTable('partner_users')) {
            throw new RuntimeException('invoice partner_users table was not found.');
        }

        $sourcePartner = $this->findSourcePartner($clientId, $sourcePartnerId);
        $targetPartnerId = $this->createTargetPartner($clientId, $sourcePartnerId, $sourcePartner);
        $this->createOrUpdatePartnerUser($clientId, $sourcePartnerId, $targetPartnerId, $sourcePartner);
    }

    /**
     * @return array<string, mixed>
     */
    private function findSourcePartner(int $clientId, int $sourcePartnerId): array
    {
        $readConnection = $this->readConnectionName();
        $schema = Schema::connection($readConnection);
        if (! $schema->hasTable('partners')) {
            throw new RuntimeException('sakemaru partners table was not found.');
        }

        $partnerColumns = array_flip($schema->getColumnListing('partners'));
        $select = ['id', 'client_id'];
        foreach (['company_id', 'bill_group_id', 'name_main'] as $column) {
            if (isset($partnerColumns[$column])) {
                $select[] = $column;
            }
        }

        $partner = DB::connection($readConnection)
            ->table('partners')
            ->where('id', $sourcePartnerId)
            ->where('client_id', $clientId)
            ->first($select);

        if ($partner === null) {
            throw new RuntimeException("sakemaru partner not found: client_id={$clientId}, partner_id={$sourcePartnerId}");
        }

        return (array) $partner;
    }

    /**
     * @param  array<string, mixed>  $sourcePartner
     */
    private function createTargetPartner(int $clientId, int $sourcePartnerId, array $sourcePartner): int
    {
        $connection = $this->writeConnectionName();
        $table = 'partners';
        $columns = array_flip(Schema::connection($connection)->getColumnListing($table));
        $now = now();

        $insert = [
            'client_id' => $clientId,
            'company_id' => $sourcePartner['company_id'] ?? null,
            'client_partner_id' => $sourcePartnerId,
            'parent_partner_id' => isset($sourcePartner['bill_group_id']) ? (int) $sourcePartner['bill_group_id'] : null,
            'name' => (string) ($sourcePartner['name_main'] ?? ''),
            'billing_email' => null,
            'is_supplier' => 0,
            'is_active' => 1,
            'can_view_group_invoices' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $payload = [];
        foreach ($insert as $column => $value) {
            if (isset($columns[$column])) {
                $payload[$column] = $value;
            }
        }

        DB::connection($connection)
            ->table($table)
            ->updateOrInsert(
                [
                    'client_id' => $clientId,
                    'client_partner_id' => $sourcePartnerId,
                ],
                $payload,
            );

        $saved = DB::connection($connection)
            ->table($table)
            ->where('client_id', $clientId)
            ->where('client_partner_id', $sourcePartnerId)
            ->first(['id']);

        if ($saved === null) {
            throw new RuntimeException('failed to create invoice partner.');
        }

        return (int) $saved->id;
    }

    /**
     * @param  array<string, mixed>  $sourcePartner
     */
    private function createOrUpdatePartnerUser(int $clientId, int $sourcePartnerId, int $targetPartnerId, array $sourcePartner): void
    {
        $connection = $this->writeConnectionName();
        $table = 'partner_users';
        $columns = array_flip(Schema::connection($connection)->getColumnListing($table));
        $email = sprintf('%d-%d@%s', $clientId, $sourcePartnerId, (string) config('invoice_partner_bootstrap.email_domain', 'sakemaru.ai'));
        $plainPassword = (string) config('invoice_partner_bootstrap.initial_password', '12345678');
        $role = (string) config('invoice_partner_bootstrap.default_role', 'admin');
        $now = now();

        $insert = [
            'partner_id' => $targetPartnerId,
            'email' => $email,
            'name' => (string) ($sourcePartner['name_main'] ?? $email),
            'password' => Hash::make($plainPassword),
            'role' => $role,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $payload = [];
        foreach ($insert as $column => $value) {
            if (isset($columns[$column])) {
                $payload[$column] = $value;
            }
        }

        if ($payload === []) {
            throw new RuntimeException('invoice partner_users table has no supported columns.');
        }

        $match = [];
        if (isset($columns['partner_id'])) {
            $match['partner_id'] = $targetPartnerId;
        } elseif (isset($columns['email'])) {
            $match['email'] = $email;
        } else {
            throw new RuntimeException('invoice partner_users table requires partner_id or email column.');
        }

        DB::connection($connection)
            ->table($table)
            ->updateOrInsert($match, $payload);
    }

    private function normalizeClosingDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (is_string($value)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return $value;
            }

            $timestamp = strtotime($value);

            return $timestamp === false ? null : date('Y-m-d', $timestamp);
        }

        return null;
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
