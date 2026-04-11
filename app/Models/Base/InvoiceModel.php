<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

abstract class InvoiceModel extends Model
{
    protected $connection = 'invoice';
}
