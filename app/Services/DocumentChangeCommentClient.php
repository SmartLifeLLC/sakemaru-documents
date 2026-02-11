<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DocumentChangeCommentClient
{
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

    private function writeConnectionName(): string
    {
        return is_array(config('database.connections.invoice_write'))
            ? 'invoice_write'
            : 'invoice';
    }
}
