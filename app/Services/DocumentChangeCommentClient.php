<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DocumentChangeCommentClient
{
    /**
     * Resolve commenter display names for a collection of comments.
     *
     * @param  Collection<int, array<string, mixed>>  $comments
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveCommenterNames(Collection $comments): Collection
    {
        $companyUserIds = $comments
            ->where('commenter_type', 'company_user')
            ->pluck('commenter_id')
            ->unique()
            ->filter()
            ->values();

        $partnerUserIds = $comments
            ->where('commenter_type', 'partner_user')
            ->pluck('commenter_id')
            ->unique()
            ->filter()
            ->values();

        $companyNames = $companyUserIds->isNotEmpty()
            ? DB::connection('sakemaru')
                ->table('users')
                ->whereIn('id', $companyUserIds)
                ->pluck('name', 'id')
            : collect();

        $partnerNames = $partnerUserIds->isNotEmpty()
            ? DB::connection($this->readConnectionName())
                ->table('partner_users')
                ->whereIn('id', $partnerUserIds)
                ->pluck('name', 'id')
            : collect();

        return $comments->map(function (array $comment) use ($companyNames, $partnerNames): array {
            $type = $comment['commenter_type'] ?? '';
            $id = $comment['commenter_id'] ?? null;

            $comment['commenter_name'] = match ($type) {
                'company_user' => $companyNames->get($id, "company_user#{$id}"),
                'partner_user' => $partnerNames->get($id, "partner_user#{$id}"),
                'system' => 'システム',
                default => "{$type}#{$id}",
            };

            return $comment;
        });
    }

    public function post(int $requestId, string $body, int|string|null $commenterId = null): bool
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException('requestId must be greater than 0');
        }

        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('body is required');
        }

        if ($commenterId === null || $commenterId === '') {
            $commenterId = auth('web')->id() ?? auth()->id();
        }

        $commenterId = is_numeric($commenterId) ? (int) $commenterId : null;
        if ($commenterId === null || $commenterId <= 0) {
            throw new RuntimeException('company user id could not be resolved.');
        }

        return DB::connection($this->writeConnectionName())
            ->table('document_change_comments')
            ->insert([
                'request_id' => $requestId,
                'commenter_type' => 'company_user',
                'commenter_id' => $commenterId,
                'body' => $body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
