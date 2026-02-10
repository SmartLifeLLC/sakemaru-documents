<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocSyncRunItem extends Model
{
    protected $connection = 'sakemaru';

    protected $table = 'doc_sync_run_items';

    protected $guarded = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
