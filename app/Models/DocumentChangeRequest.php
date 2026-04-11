<?php

namespace App\Models;

use App\Models\Base\InvoiceModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentChangeRequest extends InvoiceModel
{
    protected $connection = 'invoice_read';

    protected $table = 'document_change_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_payload_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentChangeComment::class, 'request_id');
    }

    public function invoiceDocument(): BelongsTo
    {
        return $this->belongsTo(InvoiceDocument::class, 'document_id');
    }
}
