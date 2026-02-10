<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class AuditLogService
{
    /**
     * Create an audit log entry.
     *
     * @param User|null $user
     * @param string $event
     * @param string|null $description
     * @return AuditLog
     */
    public function log(?User $user, string $event, ?string $description = null): AuditLog
    {
        try {
            return AuditLog::create([
                'user_id' => $user ? $user->id : null, // Nullable if system or unauthenticated (though usually authenticated)
                'event' => $event,
                'description' => $description,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit log write skipped.', [
                'event' => $event,
                'user_id' => $user?->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return new AuditLog();
        }
    }
}
