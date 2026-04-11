<?php

namespace App\Models\Base;

use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class SakemaruAuthenticatable extends Authenticatable
{
    protected $connection = 'sakemaru';
}
