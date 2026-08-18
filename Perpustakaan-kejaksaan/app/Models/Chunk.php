<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Potongan teks dokumen. Kolom `embedding` bertipe vector(768) dan
 * dikelola sepenuhnya oleh layanan AI -- Eloquent tidak pernah menulisnya.
 */
class Chunk extends Model
{
    protected $fillable = [
        'document_id', 'chunk_index', 'content',
        'page_start', 'page_end', 'token_count',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'page_start' => 'integer',
            'page_end' => 'integer',
            'token_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
