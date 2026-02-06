<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiResponse extends Model
{
    protected $fillable = [
        'complaint_id',
        'response_text',
        'tokens_used',
        'approved',
    ];

    protected $casts = [
        'approved' => 'boolean',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
