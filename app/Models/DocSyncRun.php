<?php

namespace App\Models;

use App\Models\Base\MySqlModel;

class DocSyncRun extends MySqlModel
{
    protected $table = 'sync_runs';

    protected $guarded = [];

    protected $casts = [
        'checkpoint_from' => 'array',
        'checkpoint_to' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
