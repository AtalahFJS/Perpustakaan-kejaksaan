<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Document;
use App\Services\KlienLayananAi;
use App\Services\PencatatAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Modul chat dengan dokumen -- RAG (PRD 5.6, alur 7.6).
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly KlienLayananAi $ai,
        private readonly PencatatAudit $audit,
    ) {}

    /** F-6.5: riwayat percakapan tersimpan per pengguna per dokumen. */
    public function show(Request $request, Document $document): View
    {
        $this->authorize('chat', $document);

        $sesi = ChatSession::firstOrCreate(
            ['user_id' => $request->user()->id, 'document_id' => $document->id],
            ['title' => 'Tanya: '.$document->title],
        );

        return view('chat.show', [
            'dokumen' => $document,
            'sesi' => $sesi,
            'pesan' => $sesi->messages()->get(),
            'sisaKuota' => $this->sisaKuota($request->user()->id),
        ]);
    }

    /**
     * F-6.1: mengajukan pertanyaan bahasa alami terhadap satu dokumen.
     * F-6.2: jawaban hanya disusun dari potongan teks dokumen.
     */
    public function tanya(Request $request, Document $document): JsonResponse
    {
        $this->authorize('chat', $document);

        $data = $request->validate([
            'pertanyaan' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'pertanyaan.required' => 'Pertanyaan tidak boleh kosong.',
            'pertanyaan.min' => 'Pertanyaan terlalu pendek.',
        ]);

        // F-7.6: batas kuota pemakaian AI per pengguna per hari.
        if ($this->sisaKuota($request->user()->id) <= 0) {
            return response()->json([
                'galat' => 'Kuota tanya jawab harian Anda sudah habis. Silakan coba lagi besok.',
            ], 429);
        }

        $sesi = ChatSession::firstOrCreate(
            ['user_id' => $request->user()->id, 'document_id' => $document->id],
            ['title' => 'Tanya: '.$document->title],
        );

        // F-6.6: percakapan mendukung pertanyaan lanjutan -- kirim
        // beberapa giliran terakhir sebagai konteks.
        $riwayat = $sesi->messages()
            ->latest('id')
            ->take(6)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        $pesanPengguna = $sesi->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => $data['pertanyaan'],
        ]);

        $this->audit->catatDokumen(PencatatAudit::CHAT_TANYA, $document, [
            'chat_session_id' => $sesi->id,
        ]);

        try {
            $hasil = $this->ai->tanya(
                $document->id,
                $data['pertanyaan'],
                $riwayat,
                $request->user()->id,
            );
        } catch (Throwable $galat) {
            // NFR 6.4: kegagalan layanan AI tidak melumpuhkan aplikasi --
            // pesan galat ditulis dalam bahasa yang dipahami pengguna.
            $pesanPengguna->delete();

            return response()->json([
                'galat' => 'Layanan AI sedang tidak dapat dihubungi. Katalog, pencarian, '
                    .'pembaca, dan ringkasan yang sudah tersimpan tetap dapat digunakan.',
                'rinci' => $galat->getMessage(),
            ], 503);
        }

        $jawaban = $sesi->messages()->create([
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $hasil['jawaban'],
            'citations' => $hasil['sitasi'] ?? [],
            'tokens_in' => $hasil['tokens_in'] ?? 0,
            'tokens_out' => $hasil['tokens_out'] ?? 0,
            'latency_ms' => $hasil['latency_ms'] ?? 0,
        ]);

        return response()->json([
            'pesan_id' => $jawaban->id,
            'jawaban' => $jawaban->content,
            'sitasi' => $jawaban->citations,
            // F-6.4: sistem menyatakan eksplisit bila informasi tidak ditemukan
            'ditemukan' => $hasil['ditemukan'] ?? true,
            'sisa_kuota' => $this->sisaKuota($request->user()->id),
        ]);
    }

    /** F-6.7: umpan balik suka / tidak suka pada tiap jawaban. */
    public function umpanBalik(Request $request, ChatMessage $message): JsonResponse
    {
        abort_unless(
            $message->chatSession->user_id === $request->user()->id,
            403,
        );

        $data = $request->validate([
            'nilai' => ['required', 'integer', 'in:-1,0,1'],
        ]);

        $message->update(['feedback' => $data['nilai'] ?: null]);

        return response()->json(['status' => 'tersimpan']);
    }

    /** Menghapus riwayat percakapan pengguna atas satu dokumen. */
    public function bersihkan(Request $request, Document $document): JsonResponse
    {
        $sesi = ChatSession::where('user_id', $request->user()->id)
            ->where('document_id', $document->id)
            ->first();

        $sesi?->messages()->delete();

        return response()->json(['status' => 'dibersihkan']);
    }

    /** F-7.6: sisa kuota tanya jawab hari ini. */
    private function sisaKuota(int $userId): int
    {
        $batas = (int) config('pustakaai.kuota.chat_per_hari');

        $terpakai = AiUsageLog::where('user_id', $userId)
            ->where('operation', 'chat')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return max(0, $batas - $terpakai);
    }
}
