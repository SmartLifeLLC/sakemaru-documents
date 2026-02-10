<?php

namespace App\Services;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class InvoiceOnboardingStartService
{
    /** @var array<string, array<string, bool>> */
    private array $columnCache = [];

    /**
     * @return array{request_id:int|null,dedupe_key:string,status:string,email:string}
     */
    public function start(Partner $partner, string $initialEmail, ?int $requestedBy = null): array
    {
        if (! (bool) config('invoice_onboarding.enabled', true)) {
            throw new RuntimeException('Invoice onboarding is disabled.');
        }

        $connection = (string) config('invoice_onboarding.connection', 'invoice');
        $table = (string) config('invoice_onboarding.table', 'onboarding_requests');
        $sourceSystem = (string) config('invoice_onboarding.source_system', 'documents');
        $defaultStatus = (string) config('invoice_onboarding.default_status', 'requested');

        if (! Schema::connection($connection)->hasTable($table)) {
            throw new RuntimeException("Invoice onboarding table is missing: {$connection}.{$table}");
        }

        $clientId = (int) ($partner->client_id ?? 0);
        $partnerId = (int) ($partner->id ?? 0);

        if ($clientId <= 0) {
            throw new InvalidArgumentException('source_client_id is required.');
        }

        if ($partnerId <= 0) {
            throw new InvalidArgumentException('source_partner_id is required.');
        }

        $email = Str::lower(trim($initialEmail));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('initial_email is invalid.');
        }

        $dedupeKey = hash('sha256', $clientId.'|'.$partnerId.'|'.$email);
        $now = now();

        $payload = [
            'source_system' => $sourceSystem,
            'source_client_id' => $clientId,
            'source_partner_id' => $partnerId,
            'initial_email' => $email,
            'request_dedupe_key' => $dedupeKey,
            'status' => $defaultStatus,
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($requestedBy !== null) {
            $payload['requested_by'] = $requestedBy;
        }

        $payload = $this->onlyExistingColumns($connection, $table, $payload);

        $requiredUniqueColumns = ['source_system', 'source_client_id', 'request_dedupe_key'];
        foreach ($requiredUniqueColumns as $column) {
            if (! $this->hasColumn($connection, $table, $column)) {
                throw new RuntimeException("Required onboarding column is missing: {$table}.{$column}");
            }
        }

        $updateColumns = array_values(array_filter([
            'initial_email',
            'status',
            'requested_at',
            'requested_by',
            'updated_at',
        ], fn (string $column): bool => array_key_exists($column, $payload)));

        DB::connection($connection)->table($table)->upsert(
            [$payload],
            $requiredUniqueColumns,
            $updateColumns,
        );

        $selectColumns = $this->existingColumns($connection, $table, [
            'id',
            'status',
            'initial_email',
        ]);

        $stored = DB::connection($connection)
            ->table($table)
            ->where('source_system', $sourceSystem)
            ->where('source_client_id', $clientId)
            ->where('request_dedupe_key', $dedupeKey)
            ->first($selectColumns !== [] ? $selectColumns : ['source_system']);

        return [
            'request_id' => isset($stored->id) ? (int) $stored->id : null,
            'dedupe_key' => $dedupeKey,
            'status' => (string) ($stored->status ?? $defaultStatus),
            'email' => (string) ($stored->initial_email ?? $email),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function onlyExistingColumns(string $connection, string $table, array $payload): array
    {
        $filtered = [];

        foreach ($payload as $column => $value) {
            if ($this->hasColumn($connection, $table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private function existingColumns(string $connection, string $table, array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn (string $column): bool => $this->hasColumn($connection, $table, $column),
        ));
    }

    private function hasColumn(string $connection, string $table, string $column): bool
    {
        $cacheKey = $connection.'::'.$table;

        if (! array_key_exists($cacheKey, $this->columnCache)) {
            $columns = Schema::connection($connection)->getColumnListing($table);
            $this->columnCache[$cacheKey] = array_fill_keys($columns, true);
        }

        return isset($this->columnCache[$cacheKey][$column]);
    }
}
