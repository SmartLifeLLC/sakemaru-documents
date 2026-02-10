<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

abstract class MySqlModel extends Model
{
    protected $connection = 'mysql';
}
