<?php

namespace App\Models;

use App\Models\Base\SakemaruModel;

class ExternalInvoice extends SakemaruModel
{
    protected $table = 'external_invoices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'file_size' => 'integer',
            'page_count' => 'integer',
            'billing_amount' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
