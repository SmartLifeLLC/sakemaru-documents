<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocSyncMapping extends Model
{
    protected $connection = 'sakemaru';

    protected $table = 'doc_sync_mappings';

    protected $guarded = [];

    protected $casts = [
        'confidence' => 'decimal:2',
        'first_synced_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'stale_at' => 'datetime',
    ];
}
