<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocSyncError extends Model
{
    protected $connection = 'sakemaru';

    protected $table = 'doc_sync_errors';

    protected $guarded = [];

    protected $casts = [
        'error_context' => 'array',
        'is_retryable' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
