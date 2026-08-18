<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    // F-8.1: setiap dokumen wajib memiliki klasifikasi
    public const KLASIFIKASI_PUBLIK = 'publik';
    public const KLASIFIKASI_INTERNAL = 'internal';
    public const KLASIFIKASI_TERBATAS = 'terbatas';

    public const SEMUA_KLASIFIKASI = [
        self::KLASIFIKASI_PUBLIK => 'Publik',
        self::KLASIFIKASI_INTERNAL => 'Internal',
        self::KLASIFIKASI_TERBATAS => 'Terbatas',
    ];

    // F-2.9: status pemrosesan
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    // Dokumen terbatas berhenti di sini -- tidak pernah diproses AI (F-8.2)
    public const STATUS_KATALOG_SAJA = 'katalog_saja';

    public const SEMUA_STATUS = [
        self::STATUS_UPLOADED => 'Menunggu diproses',
        self::STATUS_PROCESSING => 'Sedang diproses',
        self::STATUS_READY => 'Siap',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_KATALOG_SAJA => 'Katalog saja',
    ];

    protected $fillable = [
        'title', 'author', 'publisher', 'year', 'category_id', 'classification',
        'isbn', 'document_number', 'page_count', 'language', 'abstract',
        'file_path', 'file_name', 'file_size', 'file_mime', 'file_hash',
        'cover_path', 'status', 'error_message', 'ingested_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'ingested_at' => 'datetime',
            'year' => 'integer',
            'page_count' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'document_tag');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /**
     * F-8.4: batasi hasil menurut klasifikasi dan peran pengguna.
     */
    public function scopeTerlihatOleh(Builder $query, ?User $user): Builder
    {
        $klasifikasi = $user
            ? $user->klasifikasiYangBolehDilihat()
            : [self::KLASIFIKASI_PUBLIK];

        return $query->whereIn('classification', $klasifikasi);
    }

    /** F-8.2: dokumen terbatas tidak pernah dikirim ke layanan AI. */
    public function bolehDiprosesAi(): bool
    {
        return $this->classification !== self::KLASIFIKASI_TERBATAS;
    }

    public function siapDitanya(): bool
    {
        return $this->status === self::STATUS_READY && $this->bolehDiprosesAi();
    }

    public function namaKlasifikasi(): string
    {
        return self::SEMUA_KLASIFIKASI[$this->classification] ?? $this->classification;
    }

    public function namaStatus(): string
    {
        return self::SEMUA_STATUS[$this->status] ?? $this->status;
    }

    /** Warna lencana klasifikasi di antarmuka (F-8.5). */
    public function warnaKlasifikasi(): string
    {
        return match ($this->classification) {
            self::KLASIFIKASI_PUBLIK => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::KLASIFIKASI_INTERNAL => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::KLASIFIKASI_TERBATAS => 'bg-rose-100 text-rose-800 ring-rose-600/20',
            default => 'bg-slate-100 text-slate-800 ring-slate-600/20',
        };
    }

    public function ukuranTerbaca(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
