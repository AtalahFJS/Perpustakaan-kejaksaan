<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'document_id', 'operation', 'provider', 'model',
        'tokens_in', 'tokens_out', 'cost_estimate', 'latency_ms',
        'berhasil', 'error_message', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_estimate' => 'decimal:6',
            'berhasil' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
