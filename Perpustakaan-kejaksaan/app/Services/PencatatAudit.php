<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * F-7.2: mencatat setiap akses dokumen -- siapa, dokumen apa, kapan, aksi apa.
 * Log disimpan minimal satu tahun (NFR 6.3).
 */
class PencatatAudit
{
    public const DOKUMEN_LIHAT = 'dokumen.lihat';
    public const DOKUMEN_BACA = 'dokumen.baca';
    public const DOKUMEN_UNDUH = 'dokumen.unduh';
    public const DOKUMEN_UNGGAH = 'dokumen.unggah';
    public const DOKUMEN_UBAH = 'dokumen.ubah';
    public const DOKUMEN_HAPUS = 'dokumen.hapus';
    public const DOKUMEN_PULIHKAN = 'dokumen.pulihkan';
    public const DOKUMEN_INGEST_ULANG = 'dokumen.ingest_ulang';
    public const CHAT_TANYA = 'chat.tanya';
    public const PENGGUNA_BUAT = 'pengguna.buat';
    public const PENGGUNA_UBAH = 'pengguna.ubah';
    public const MASUK = 'auth.masuk';
    public const KELUAR = 'auth.keluar';

    public function catat(string $aksi, ?Model $subjek = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $aksi,
            'subject_type' => $subjek ? class_basename($subjek) : null,
            'subject_id' => $subjek?->getKey(),
            'ip_address' => Request::ip(),
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    /** Pintasan untuk aksi terhadap dokumen -- selalu menyertakan klasifikasi. */
    public function catatDokumen(string $aksi, Document $dokumen, array $metadata = []): void
    {
        $this->catat($aksi, $dokumen, array_merge([
            'judul' => $dokumen->title,
            'klasifikasi' => $dokumen->classification,
        ], $metadata));
    }
}
