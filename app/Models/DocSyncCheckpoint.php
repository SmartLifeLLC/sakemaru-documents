<?php

namespace App\Models;

use App\Models\Base\MySqlModel;

class DocSyncCheckpoint extends MySqlModel
{
    protected $table = 'sync_checkpoints';

    protected $guarded = [];

    protected $casts = [
        'cursor_updated_at' => 'datetime',
    ];
}
