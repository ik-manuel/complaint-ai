<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $filable = [
        'document_id',
        'chunk_index',
        'page_number',
        'content',
        'token_count',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
