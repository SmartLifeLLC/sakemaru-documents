<?php

namespace App\Models;

use App\Models\Base\MySqlModel;

class DocSyncRunItem extends MySqlModel
{
    protected $table = 'sync_run_items';

    protected $guarded = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
