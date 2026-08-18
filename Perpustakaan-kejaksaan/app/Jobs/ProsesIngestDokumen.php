<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\KlienLayananAi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Alur ingest dokumen (PRD 7.5).
 *
 * Dijalankan asinkron lewat antrean supaya dokumen tebal tidak
 * memblokir permintaan HTTP (NFR 6.1).
 */
class ProsesIngestDokumen implements ShouldQueue
{
    use Queueable;

    /** NFR 6.4: percobaan ulang dengan exponential backoff. */
    public int $tries = 3;

    public int $timeout = 1800;

    public array $backoff = [60, 300];

    public function __construct(public readonly int $documentId) {}

    public function handle(KlienLayananAi $ai): void
    {
        $dokumen = Document::find($this->documentId);

        if (! $dokumen) {
            return;
        }

        // F-8.2: pengaman berlapis -- dokumen terbatas tidak pernah
        // dikirim ke layanan AI, bahkan jika pekerjaan sudah terlanjur
        // masuk antrean sebelum klasifikasinya diubah.
        if (! $dokumen->bolehDiprosesAi()) {
            $dokumen->update(['status' => Document::STATUS_KATALOG_SAJA]);

            return;
        }

        $dokumen->update([
            'status' => Document::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        $hasil = $ai->ingest(
            $dokumen->id,
            Storage::path($dokumen->file_path),
        );

        $dokumen->update([
            'status' => Document::STATUS_READY,
            'page_count' => $hasil['halaman'] ?? $dokumen->page_count,
            'cover_path' => $hasil['sampul'] ?? $dokumen->cover_path,
            'ingested_at' => now(),
            'error_message' => null,
        ]);

        // R4: dokumen hasil pindaian ditandai agar dapat ditinjau manual.
        if (($hasil['perlu_ocr'] ?? false) === true) {
            Log::warning('Dokumen memerlukan OCR', [
                'document_id' => $dokumen->id,
                'judul' => $dokumen->title,
            ]);
        }
    }

    /**
     * F-2.9 / F-2.10: kegagalan tercatat dengan pesan yang dapat dibaca
     * pustakawan, dan dokumen dapat diproses ulang dari antarmuka.
     */
    public function failed(Throwable $galat): void
    {
        Document::where('id', $this->documentId)->update([
            'status' => Document::STATUS_FAILED,
            'error_message' => mb_substr($galat->getMessage(), 0, 1000),
        ]);

        Log::error('Ingest dokumen gagal', [
            'document_id' => $this->documentId,
            'pesan' => $galat->getMessage(),
        ]);
    }
}
