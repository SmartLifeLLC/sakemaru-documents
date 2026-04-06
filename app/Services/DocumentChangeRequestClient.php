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
            ->orderBy('id')
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
                'resolved_at' => in_array($status, ['resolved', 'rejected'], true) ? now() : null,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    /**
     * Get request IDs that are unread from the admin perspective.
     *
     * @param  list<int>  $requestIds
     * @return list<int>
     */
    public function getUnreadRequestIds(array $requestIds): array
    {
        if ($requestIds === []) {
            return [];
        }

        return DB::connection($this->readConnectionName())
            ->table('document_change_comments')
            ->selectRaw("
                request_id,
                MAX(CASE WHEN commenter_type = 'company_user' THEN created_at END) AS last_company_at,
                MAX(CASE WHEN commenter_type = 'partner_user' THEN created_at END) AS last_partner_at
            ")
            ->whereIn('request_id', $requestIds)
            ->groupBy('request_id')
            ->get()
            ->filter(function (object $row): bool {
                if (! $row->last_partner_at) {
                    return false;
                }

                return ! $row->last_company_at || $row->last_partner_at > $row->last_company_at;
            })
            ->pluck('request_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
