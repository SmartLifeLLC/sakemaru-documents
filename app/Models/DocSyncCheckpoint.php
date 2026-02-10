<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocSyncCheckpoint extends Model
{
    protected $connection = 'sakemaru';

    protected $table = 'doc_sync_checkpoints';

    protected $guarded = [];

    protected $casts = [
        'cursor_updated_at' => 'datetime',
    ];
}
