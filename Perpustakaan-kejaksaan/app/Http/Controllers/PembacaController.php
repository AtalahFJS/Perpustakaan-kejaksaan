<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modul pembaca dokumen di peramban (PRD 5.4).
 */
class PembacaController extends Controller
{
    /**
     * F-4.1 membaca tanpa mengunduh, F-4.2 navigasi halaman dan zoom,
     * F-4.4 melompat ke halaman tertentu lewat parameter ?halaman=.
     */
    public function show(Request $request, Document $document): View
    {
        $this->authorize('read', $document);

        return view('pembaca.show', [
            'dokumen' => $document,
            'halamanAwal' => max(1, $request->integer('halaman', 1)),
            // F-4.5: lanjutkan dari halaman terakhir yang dibaca
            'halamanTersimpan' => $this->penandaHalaman($request, $document->id),
        ]);
    }

    /** F-4.5: penanda halaman terakhir dibaca, per pengguna per dokumen. */
    public function simpanPenanda(Request $request, Document $document): JsonResponse
    {
        $this->authorize('read', $document);

        $data = $request->validate([
            'halaman' => ['required', 'integer', 'min:1'],
        ]);

        $penanda = $request->session()->get('penanda_halaman', []);
        $penanda[$document->id] = $data['halaman'];
        $request->session()->put('penanda_halaman', $penanda);

        return response()->json(['status' => 'tersimpan']);
    }

    private function penandaHalaman(Request $request, int $documentId): ?int
    {
        return $request->session()->get('penanda_halaman', [])[$documentId] ?? null;
    }
}
