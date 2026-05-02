<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\DocumentStatus;

class Document extends Model
{
    protected $fillable = [
        'title',
        'filename',
        'mime_type',
        'file_size',
        'total_chunks',
        'total_pages',
        'status',
        'error_message',
    ];

    protected $casts = [
        'status' => DocumentStatus::class,
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function isProcessed(): bool 
    {
        return $this->status === 'completed';
    }
}
