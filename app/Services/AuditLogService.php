<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

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
        return AuditLog::create([
            'user_id' => $user ? $user->id : null, // Nullable if system or unauthenticated (though usually authenticated)
            'event' => $event,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
