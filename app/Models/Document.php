<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $connection = 'sakemaru';

    protected $table = 'documents';

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
