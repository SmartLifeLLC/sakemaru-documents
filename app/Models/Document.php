<?php

namespace App\Models;

use App\Models\Base\MySqlModel;

class Document extends MySqlModel
{
    protected $fillable = [
        'partner_id',
        'category',
        'source_type',
        'transaction_date',
        'partner_name',
        'amount',
        's3_path',
        'status',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:0',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
