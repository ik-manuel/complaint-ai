<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tokens',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Estimate tokens for a message (rough calculation)
     * Week 1 concept: 1 token ≈ 0.75 words
     */
    public static function estimateTokens(string $content): int
    {
        $words = str_word_count($content);
        return (int) ceil($words / 0.75);
    }

    /**
     * Format message for API
     */
    public function toApiFormat(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}