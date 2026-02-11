<?php

namespace App\Models;

use App\Models\Base\InvoiceModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChangeComment extends InvoiceModel
{
    protected $connection = 'invoice_read';

    protected $table = 'document_change_comments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DocumentChangeRequest::class, 'request_id');
    }
}
