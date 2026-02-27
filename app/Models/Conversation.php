<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'complaint_id',
        'system_prompt',
        'summary',
        'messages_summarized_count',
        'total_tokens',
        'last_summarized_at',
    ];

    protected $casts = [
        'last_summarized_at' => 'datetime',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get recent messages (for context window)
     */
    public function recentMessages(int $limit = 10): HasMany
    {
        return $this->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    /**
     * Calculate total tokens in conversation
     */
    public function calculateTotalTokens(): int
    {
        return $this->messages()->sum('tokens');
    }

    /**
     * Check if conversation needs summarization
     * Week 3 Day 3 concept: Auto-summarize when too long
     */
    public function needsSummarization(): bool
    {
        $messageCount = $this->messages()->count();
        $tokenCount = $this->calculateTotalTokens();
        
        // Summarize if more than 20 messages or 5000 tokens
        return $messageCount > 20 || $tokenCount > 5000;
    }
}