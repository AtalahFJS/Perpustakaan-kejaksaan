<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Summary extends Model
{
    // F-5.2: tiga tingkat ringkasan
    public const TIPE_SINGKAT = 'singkat';
    public const TIPE_POIN_KUNCI = 'poin_kunci';
    public const TIPE_PER_BAGIAN = 'per_bagian';

    public const SEMUA_TIPE = [
        self::TIPE_SINGKAT => 'Ringkasan singkat',
        self::TIPE_POIN_KUNCI => 'Poin-poin kunci',
        self::TIPE_PER_BAGIAN => 'Ringkasan per bagian',
    ];

    protected $fillable = [
        'document_id', 'type', 'scope_label', 'urutan', 'content',
        'model_used', 'is_approved', 'approved_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'generated_at' => 'datetime',
            'urutan' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function namaTipe(): string
    {
        return self::SEMUA_TIPE[$this->type] ?? $this->type;
    }
}
