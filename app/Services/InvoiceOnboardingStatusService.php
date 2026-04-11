<?php

namespace App\Services;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceOnboardingStatusService
{
    /** @var array<string, array{request_id:int|null,status:?string,initial_email:?string,requested_at:?string,processed_at:?string}|null> */
    private array $partnerCache = [];

    /** @var array<string, array<string, bool>> */
    private array $columnCache = [];

    /**
     * @return array{request_id:int|null,status:?string,initial_email:?string,requested_at:?string,processed_at:?string}|null
     */
    public function latestForPartner(Partner $partner): ?array
    {
        if (! (bool) config('invoice_onboarding.enabled', true)) {
            return null;
        }

        $connection = (string) config('invoice_onboarding.connection', 'invoice');
        $table = (string) config('invoice_onboarding.table', 'onboarding_requests');
        $sourceSystem = (string) config('invoice_onboarding.source_system', 'documents');
        $clientId = (int) ($partner->client_id ?? 0);
        $partnerId = (int) ($partner->id ?? 0);

        if ($clientId <= 0 || $partnerId <= 0) {
            return null;
        }

        $cacheKey = $clientId.'::'.$partnerId;
        if (array_key_exists($cacheKey, $this->partnerCache)) {
            return $this->partnerCache[$cacheKey];
        }

        if (! Schema::connection($connection)->hasTable($table)) {
            $this->partnerCache[$cacheKey] = null;

            return null;
        }

        $requiredColumns = ['source_system', 'source_client_id', 'source_partner_id'];
        foreach ($requiredColumns as $column) {
            if (! $this->hasColumn($connection, $table, $column)) {
                $this->partnerCache[$cacheKey] = null;

                return null;
            }
        }

        $selectColumns = $this->existingColumns($connection, $table, [
            'id',
            'status',
            'initial_email',
            'requested_at',
            'processed_at',
        ]);

        $query = DB::connection($connection)
            ->table($table)
            ->where('source_system', $sourceSystem)
            ->where('source_client_id', $clientId)
            ->where('source_partner_id', $partnerId);

        if ($this->hasColumn($connection, $table, 'requested_at')) {
            $query->orderByDesc('requested_at');
        }

        if ($this->hasColumn($connection, $table, 'id')) {
            $query->orderByDesc('id');
        }

        $row = $query->first($selectColumns !== [] ? $selectColumns : ['source_system']);

        if (! $row) {
            $this->partnerCache[$cacheKey] = null;

            return null;
        }

        $result = [
            'request_id' => isset($row->id) ? (int) $row->id : null,
            'status' => $row->status ?? null,
            'initial_email' => $row->initial_email ?? null,
            'requested_at' => isset($row->requested_at) ? (string) $row->requested_at : null,
            'processed_at' => isset($row->processed_at) ? (string) $row->processed_at : null,
        ];

        $this->partnerCache[$cacheKey] = $result;

        return $result;
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
            $this->columnCache[$cacheKey] = array_fill_keys(
                Schema::connection($connection)->getColumnListing($table),
                true,
            );
        }

        return isset($this->columnCache[$cacheKey][$column]);
    }
}
