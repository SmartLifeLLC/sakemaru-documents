<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

abstract class SakemaruModel extends Model
{
    protected $connection = 'sakemaru';
}
