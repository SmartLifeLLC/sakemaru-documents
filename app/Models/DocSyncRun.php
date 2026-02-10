<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocSyncRun extends Model
{
    protected $connection = 'sakemaru';

    protected $table = 'doc_sync_runs';

    protected $guarded = [];

    protected $casts = [
        'checkpoint_from' => 'array',
        'checkpoint_to' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
