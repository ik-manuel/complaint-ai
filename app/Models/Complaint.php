<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Complaint extends Model
{
    protected $fillable = [
        'customer_id',
        'ticket_number',
        'subject',
        'message',
        'urgency',
        'category',
        'status',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($complaint) {
            $complaint->ticket_number = 'TKT-' . strtoupper(uniqid());
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function aiResponse(): HasOne
    {
        return $this->hasOne(AiResponse::class);
    }
}
