<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\PencatatAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu-satunya jalan menuju berkas dokumen.
 *
 * F-4.3 / NFR 6.2: berkas disimpan di storage/app/private sehingga tidak
 * dapat diakses lewat URL langsung. Setiap permintaan melewati controller
 * ini yang memeriksa otorisasi dan mencatat aksesnya ke log audit.
 */
class BerkasController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * F-4.1: menyajikan PDF untuk dibaca di peramban (inline),
     * tanpa pengguna perlu mengunduhnya.
     */
    public function berkas(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('read', $document);

        abort_unless(Storage::exists($document->file_path), 404, 'Berkas tidak ditemukan.');

        // Hanya catat permintaan awal, bukan setiap permintaan rentang byte
        // yang dikirim pembaca PDF saat menggulir.
        if (! $request->headers->has('Range')) {
            $this->audit->catatDokumen(PencatatAudit::DOKUMEN_BACA, $document);
        }

        return Storage::response(
            $document->file_path,
            $document->file_name,
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
            'inline',
        );
    }

    /**
     * F-4.7: unduh berkas asli. Dokumen terbatas hanya dapat diunduh
     * pustakawan -- diatur di DocumentPolicy::download().
     */
    public function unduh(Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        abort_unless(Storage::exists($document->file_path), 404, 'Berkas tidak ditemukan.');

        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_UNDUH, $document);

        return Storage::download($document->file_path, $document->file_name);
    }

    /** F-2.11: sampul yang dibuat otomatis dari halaman pertama. */
    public function sampul(Document $document): Response|StreamedResponse
    {
        $this->authorize('view', $document);

        if (! $document->cover_path || ! Storage::exists($document->cover_path)) {
            abort(404);
        }

        return Storage::response($document->cover_path, 'sampul.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
