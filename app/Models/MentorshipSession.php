<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module M3 AI Chatbot & Mentorship, developed by Shyam Murti (S11229913).
 */
class MentorshipSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentor_id',
        'mentee_id',
        'topic',
        'scheduled_at',
        'status',
        'escalated_from_chatbot',
        'rating',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'escalated_from_chatbot' => 'boolean',
            'rating' => 'integer',
        ];
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }
}
