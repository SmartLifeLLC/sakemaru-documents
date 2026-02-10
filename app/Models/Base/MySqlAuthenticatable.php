<?php

namespace App\Models\Base;

use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class MySqlAuthenticatable extends Authenticatable
{
    protected $connection = 'mysql';
}
