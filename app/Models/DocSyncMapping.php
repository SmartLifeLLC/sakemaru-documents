<?php

namespace App\Models;

use App\Models\Base\MySqlModel;

class DocSyncMapping extends MySqlModel
{
    protected $table = 'sync_mappings';

    protected $guarded = [];

    protected $casts = [
        'confidence' => 'decimal:2',
        'first_synced_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'stale_at' => 'datetime',
    ];
}
