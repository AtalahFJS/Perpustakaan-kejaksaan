<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\KlienLayananAi;
use App\Services\PencatatAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Asisten tanya-jawab lintas dokumen (pembaruan 26 Agustus 2026).
 *
 * Berbeda dari ChatController yang terikat pada satu dokumen, di sini
 * pertanyaan dijawab dari seluruh koleksi menurut relevansinya.
 *
 * Hanya untuk pengguna yang sudah masuk -- sama seperti tanya-jawab per
 * dokumen, agar kuota dan biaya layanan AI tidak dapat disalahgunakan
 * pengunjung anonim.
 */
class AsistenController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function tanya(Request $request, KlienLayananAi $ai): JsonResponse
    {
        $data = $request->validate([
            'pertanyaan' => ['required', 'string', 'max:2000'],
            // Riwayat dikirim widget agar pertanyaan lanjutan punya konteks.
            // Dibatasi supaya tidak dipakai menggelembungkan biaya token.
            'riwayat' => ['array', 'max:12'],
            'riwayat.*.role' => ['required', 'in:user,assistant'],
            'riwayat.*.content' => ['required', 'string', 'max:4000'],
        ], [
            'pertanyaan.required' => 'Tuliskan pertanyaan Anda terlebih dahulu.',
        ]);

        // Batas akses ditegakkan di sini, bukan di layanan AI: hanya dokumen
        // yang boleh dilihat penanya DAN diizinkan diproses AI yang dikirim.
        $bolehDibaca = Document::query()
            ->terlihatOleh($request->user())
            ->where('ai_allowed', true)
            ->where('status', Document::STATUS_READY)
            ->pluck('id')
            ->all();

        if ($bolehDibaca === []) {
            return response()->json([
                'jawaban' => 'Belum ada dokumen yang siap ditanyakan.',
                'sitasi' => [],
                'ditemukan' => false,
            ]);
        }

        try {
            $hasil = $ai->tanyaLintas(
                $bolehDibaca,
                $data['pertanyaan'],
                $data['riwayat'] ?? [],
                $request->user()->id,
            );
        } catch (Throwable $galat) {
            report($galat);

            return response()->json([
                'galat' => 'Layanan AI sedang tidak dapat dihubungi. Katalog dan pencarian tetap berjalan normal.',
            ], 503);
        }

        $this->audit->catat(PencatatAudit::CHAT_TANYA, null, [
            'lintas_dokumen' => true,
            'jumlah_dokumen' => count($bolehDibaca),
        ]);

        // Sitasi dilengkapi tautan, supaya jawaban AI selalu dapat ditelusuri
        // balik ke dokumen aslinya (F-6.4).
        $sitasi = collect($hasil['sitasi'] ?? [])->map(fn (array $s) => [
            ...$s,
            'tautan' => isset($s['document_id'])
                ? route('katalog.show', $s['document_id'])
                : null,
        ])->all();

        return response()->json([...$hasil, 'sitasi' => $sitasi]);
    }
}
