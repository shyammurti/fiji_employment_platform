<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Career Hub chat session. Messages (REQ-4) belong to a conversation so
 * a jobseeker can start a new chat and return to earlier ones.
 *
 * Module M3 AI Chatbot & Mentorship, developed by Shyam Murti (S11229913).
 */
class ChatConversation extends Model
{
    protected $fillable = ['user_id', 'title', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class)->orderBy('id');
    }
}
