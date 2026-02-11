<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentChangeRequestClient
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getComments(int $requestId): Collection
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException('requestId must be greater than 0');
        }

        return DB::connection($this->readConnectionName())
            ->table('document_change_comments')
            ->where('request_id', $requestId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (object $row): array => (array) $row);
    }

    public function updateStatus(int $requestId, string $status): bool
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException('requestId must be greater than 0');
        }

        $status = trim($status);
        if (! in_array($status, ['open', 'in_review', 'resolved', 'rejected', 'canceled'], true)) {
            throw new InvalidArgumentException('status is invalid');
        }

        $updated = DB::connection($this->writeConnectionName())
            ->table('document_change_requests')
            ->where('id', $requestId)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    private function readConnectionName(): string
    {
        return is_array(config('database.connections.invoice_read'))
            ? 'invoice_read'
            : 'invoice';
    }

    private function writeConnectionName(): string
    {
        return is_array(config('database.connections.invoice_write'))
            ? 'invoice_write'
            : 'invoice';
    }
}
