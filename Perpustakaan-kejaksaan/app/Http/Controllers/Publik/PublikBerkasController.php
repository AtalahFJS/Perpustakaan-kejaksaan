<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\PencatatAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Jalur berkas untuk pengunjung tanpa login -- hanya dokumen publik
 * (pembaruan 21 Agustus 2026). Sama seperti BerkasController, berkas
 * tidak pernah diakses langsung lewat storage; unduh berkas asli dan
 * dokumen non-publik tetap mewajibkan login (lihat BerkasController).
 */
class PublikBerkasController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function berkas(Request $request, Document $document): StreamedResponse
    {
        abort_unless($document->bolehDilihatOleh(null), 404);

        abort_unless(Storage::exists($document->file_path), 404, 'Berkas tidak ditemukan.');

        if (! $request->headers->has('Range')) {
            $this->audit->catatDokumen(PencatatAudit::DOKUMEN_BACA, $document, ['akses' => 'publik']);
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

    public function sampul(Document $document): Response|StreamedResponse
    {
        abort_unless($document->bolehDilihatOleh(null), 404);

        if (! $document->cover_path || ! Storage::exists($document->cover_path)) {
            abort(404);
        }

        return Storage::response($document->cover_path, 'sampul.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
