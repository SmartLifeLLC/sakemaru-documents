<?php

namespace App\Models;

use App\Models\Base\InvoiceModel;

class InvoiceDocument extends InvoiceModel
{
    protected $connection = 'invoice_read';

    protected $table = 'documents';

    protected $guarded = [];
}
