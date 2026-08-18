<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'chat_session_id', 'role', 'content', 'citations',
        'tokens_in', 'tokens_out', 'latency_ms', 'feedback',
    ];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'latency_ms' => 'integer',
            'feedback' => 'integer',
        ];
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }
}
