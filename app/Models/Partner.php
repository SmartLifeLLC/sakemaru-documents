<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $connection = 'sakemaru';

    protected $fillable = [
        'code',
        'name',
        'is_supplier',
    ];

    protected $casts = [
        'is_supplier' => 'boolean',
    ];
}
