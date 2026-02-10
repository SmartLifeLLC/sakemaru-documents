<?php

namespace App\Models;

use App\Models\Base\MySqlModel;

class DocSyncError extends MySqlModel
{
    protected $table = 'sync_errors';

    protected $guarded = [];

    protected $casts = [
        'error_context' => 'array',
        'is_retryable' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
