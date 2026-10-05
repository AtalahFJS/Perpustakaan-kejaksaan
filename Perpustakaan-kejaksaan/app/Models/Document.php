<?php

namespace App\Models;

use App\Services\PencatatAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    use SoftDeletes;

    /**
     * F-8.1: tingkat akses dokumen, dipilih admin saat unggah.
     *
     * Pembaruan 26 Agustus 2026: kembali ditentukan per dokumen, tidak lagi
     * diturunkan dari kategori -- kategori kini pengelompokan murni. Izin
     * pemrosesan AI berdiri sendiri di kolom `ai_allowed`.
     */
    public const KLASIFIKASI_PUBLIK = 'publik';
    public const KLASIFIKASI_INTERNAL = 'internal';

    public const SEMUA_KLASIFIKASI = [
        self::KLASIFIKASI_PUBLIK => 'Publik',
        self::KLASIFIKASI_INTERNAL => 'Internal',
    ];

    // F-2.9: status pemrosesan
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    // Dokumen yang tidak diizinkan diproses AI berhenti di sini (F-8.2)
    public const STATUS_KATALOG_SAJA = 'katalog_saja';

    public const SEMUA_STATUS = [
        self::STATUS_UPLOADED => 'Menunggu diproses',
        self::STATUS_PROCESSING => 'Sedang diproses',
        self::STATUS_READY => 'Siap',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_KATALOG_SAJA => 'Katalog saja',
    ];

    protected $fillable = [
        'title', 'author', 'publisher', 'year', 'tanggal_terbit', 'category_id',
        'classification', 'ai_allowed',
        'isbn', 'document_number', 'page_count', 'language', 'abstract',
        'file_path', 'file_name', 'file_size', 'file_mime', 'file_hash',
        'cover_path', 'status', 'error_message', 'ingested_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'ingested_at' => 'datetime',
            'tanggal_terbit' => 'date',
            'ai_allowed' => 'boolean',
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
     * Seluruh catatan pembukaan detail dokumen pada log audit F-7.2.
     *
     * Perhatikan: satu orang dapat menghasilkan banyak baris di sini --
     * setiap kali halaman detail dibuka ulang, termasuk saat kembali dari
     * pembaca atau sekadar memuat ulang. Itu memang disengaja, sebab log
     * audit harus mencatat tiap akses. Untuk angka "Dilihat", pakai
     * scopeWithJumlahDilihat() yang menghitung orangnya, bukan barisnya.
     */
    public function viewLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')
            ->where('subject_type', class_basename(self::class))
            ->where('action', PencatatAudit::DOKUMEN_LIHAT);
    }

    /**
     * F-3.1: pencarian satu kotak pada penanda identitas dokumen --
     * judul, penulis, tag, dan nomor dokumen.
     *
     * Sengaja TIDAK mencari isi dokumen maupun abstrak (pembaruan
     * 24 Agustus 2026). Keduanya panjang dan naratif, sehingga satu kata
     * umum di dalamnya menarik banyak dokumen yang tidak relevan dan
     * membuat hasil pencarian kehilangan ketajamannya. Isi dokumen tetap
     * dipakai untuk tanya-jawab AI, hanya tidak untuk pencarian katalog.
     *
     * Kolom `search_vector` (judul, penulis, nomor dokumen) menangani
     * pencocokan per kata; tag tersimpan di tabel terpisah lewat relasi
     * banyak-ke-banyak sehingga mustahil ikut ke dalam kolom generated itu
     * dan harus dicari lewat klausa tersendiri.
     *
     * Ditaruh di model, bukan di masing-masing controller: jalur publik dan
     * jalur internal wajib mencari dengan cara yang sama persis. Sebelumnya
     * kode ini terduplikasi di dua controller dan berisiko berbeda diam-diam.
     */
    public function scopePencarian(Builder $query, string $kata): Builder
    {
        return $query->where(function (Builder $q) use ($kata) {
            $suka = '%'.$kata.'%';

            $q->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$kata])
                // ilike melengkapi vektor untuk pencocokan sebagian kata.
                ->orWhere('title', 'ilike', $suka)
                ->orWhere('author', 'ilike', $suka)
                ->orWhere('document_number', 'ilike', $suka)
                ->orWhereHas('tags', fn (Builder $tag) => $tag->where('name', 'ilike', $suka));
        });
    }

    /**
     * Menambahkan kolom `dilihat_count`: berapa ORANG berbeda yang pernah
     * membuka dokumen ini -- bukan berapa kali dibuka.
     *
     * Sebelumnya memakai withCount('viewLogs') sehingga tiap pemuatan
     * halaman detail menambah angka; membuka dokumen lalu menekan "Baca
     * Dokumen" dan kembali sudah terhitung dua kali. Di sini pengguna yang
     * sudah masuk dibedakan menurut user_id, sedangkan pengunjung tanpa
     * login menurut alamat IP -- satu-satunya penanda yang tersedia bagi
     * mereka. Dihitung lewat subkueri, bukan join, agar tetap satu kueri
     * dan tidak menggandakan baris dokumen.
     */
    public function scopeWithJumlahDilihat(Builder $query): Builder
    {
        return $query->withCount(['viewLogs as dilihat_count' => function ($q) {
            $q->select(DB::raw(
                "COUNT(DISTINCT COALESCE(user_id::text, 'ip:' || COALESCE(ip_address, 'tanpa-ip')))"
            ));
        }]);
    }

    /**
     * F-8.4: batasi hasil menurut tingkat akses dokumen itu sendiri.
     *
     * Pembaruan 26 Agustus 2026: kategori tidak lagi ikut menentukan.
     * Akses sepenuhnya ditentukan `classification` yang dipilih admin saat
     * unggah. $user null berarti pengunjung tanpa login -- hanya dokumen
     * berklasifikasi publik yang tampil, apa pun kategorinya.
     */
    public function scopeTerlihatOleh(Builder $query, ?User $user): Builder
    {
        return $query->whereIn('classification', $user
            ? $user->klasifikasiYangBolehDilihat()
            : [self::KLASIFIKASI_PUBLIK]);
    }

    /**
     * Pemeriksaan satu dokumen (bukan daftar) -- dipakai controller
     * publik yang tidak punya konteks pengguna masuk untuk memutuskan
     * boleh-tidaknya menampilkan satu dokumen tertentu.
     */
    public function bolehDilihatOleh(?User $user): bool
    {
        $klasifikasi = $user
            ? $user->klasifikasiYangBolehDilihat()
            : [self::KLASIFIKASI_PUBLIK];

        return in_array($this->classification, $klasifikasi, true);
    }

    /**
     * F-8.2: dokumen yang tidak diizinkan tidak pernah dikirim ke layanan AI.
     * Sejak 23 Agustus 2026 ini ditentukan kolom `ai_allowed` sendiri, lepas
     * dari siapa yang boleh melihat dokumennya.
     */
    public function bolehDiprosesAi(): bool
    {
        return (bool) $this->ai_allowed;
    }

    /**
     * Dokumen yang tertahan di antrean lebih lama dari yang wajar.
     *
     * Pemrosesan yang gagal di tengah jalan tidak selalu meninggalkan status
     * "gagal": pekerjaan bisa hilang dari antrean tanpa jejak, misalnya saat
     * worker dimatikan atau menjalankan kode yang sudah usang. Tanpa penanda
     * ini dokumen tersebut akan diam selamanya bertuliskan "Menunggu
     * diproses", dan admin tidak punya cara memulihkannya.
     *
     * Ambangnya 15 menit -- jauh di atas waktu wajar satu dokumen, sehingga
     * dokumen yang memang sedang diproses tidak ikut tertandai.
     */
    public function tersangkut(): bool
    {
        if (! in_array($this->status, [self::STATUS_UPLOADED, self::STATUS_PROCESSING], true)) {
            return false;
        }

        return $this->updated_at !== null && $this->updated_at->lt(now()->subMinutes(15));
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
