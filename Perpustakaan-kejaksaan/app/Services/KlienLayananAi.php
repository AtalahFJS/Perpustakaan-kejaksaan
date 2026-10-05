<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pembungkus HTTP untuk layanan AI (FastAPI).
 *
 * Semua pekerjaan berat -- ekstraksi PDF, chunking, embedding, pemanggilan
 * LLM -- ada di sisi Python (PRD 7.2). Laravel hanya memanggil dan
 * menyimpan hasilnya, sehingga penyedia LLM dapat diganti tanpa
 * menyentuh kode aplikasi utama (NFR 6.6).
 */
class KlienLayananAi
{
    public function __construct(
        private readonly ?string $url = null,
        private readonly ?string $token = null,
    ) {}

    private function basis(): string
    {
        return rtrim($this->url ?? (string) config('pustakaai.ai.url'), '/');
    }

    private function permintaan(int $timeout): PendingRequest
    {
        return Http::baseUrl($this->basis())
            ->timeout($timeout)
            ->connectTimeout(10)
            // NFR 6.4: kegagalan pemanggilan ditangani dengan retry
            // dan exponential backoff.
            ->retry(3, 1000, throw: false)
            ->withHeaders([
                'X-Internal-Token' => $this->token ?? (string) config('pustakaai.ai.token'),
                'Accept' => 'application/json',
            ]);
    }

    /**
     * Memproses satu dokumen: ekstraksi -> chunking -> embedding ->
     * ringkasan bertingkat. Dipanggil dari antrean (PRD 7.5).
     *
     * @return array{halaman:int, potongan:int, ringkasan:int, sampul:?string, perlu_ocr:bool}
     */
    public function ingest(int $documentId, string $lintasanBerkas): array
    {
        $respons = $this->permintaan((int) config('pustakaai.ai.timeout'))
            ->post('/ingest', [
                'document_id' => $documentId,
                'file_path' => $lintasanBerkas,
            ]);

        if ($respons->failed()) {
            throw new RuntimeException(
                'Layanan AI menolak permintaan ingest: '.$this->pesanGalat($respons->json(), $respons->body())
            );
        }

        return $respons->json();
    }

    /**
     * Menjawab pertanyaan atas satu dokumen (PRD 7.6).
     *
     * @param  array<int, array{role:string, content:string}>  $riwayat
     * @return array{jawaban:string, sitasi:array, tokens_in:int, tokens_out:int, latency_ms:int, ditemukan:bool}
     */
    public function tanya(int $documentId, string $pertanyaan, array $riwayat = [], ?int $userId = null): array
    {
        $respons = $this->permintaan((int) config('pustakaai.ai.timeout_chat'))
            ->post('/chat', [
                'document_id' => $documentId,
                'question' => $pertanyaan,
                'history' => $riwayat,
                'user_id' => $userId,
            ]);

        if ($respons->failed()) {
            throw new RuntimeException(
                'Layanan AI tidak dapat menjawab: '.$this->pesanGalat($respons->json(), $respons->body())
            );
        }

        return $respons->json();
    }

    /**
     * Tanya-jawab menurut relevansi, mencakup beberapa dokumen sekaligus.
     *
     * Daftar dokumen disusun pemanggil dari yang benar-benar boleh dilihat
     * penanya; layanan AI tidak pernah melebarkannya sendiri.
     *
     * @param  list<int>  $documentIds
     */
    public function tanyaLintas(
        array $documentIds,
        string $pertanyaan,
        array $riwayat = [],
        ?int $userId = null,
    ): array {
        $respons = $this->permintaan((int) config('pustakaai.ai.timeout_chat'))
            ->post('/chat-lintas', [
                'document_ids' => array_values($documentIds),
                'question' => $pertanyaan,
                'history' => $riwayat,
                'user_id' => $userId,
            ]);

        if ($respons->failed()) {
            throw new RuntimeException(
                'Layanan AI tidak dapat menjawab: '.$this->pesanGalat($respons->json(), $respons->body())
            );
        }

        return $respons->json();
    }

    /** F-5.6: admin memicu pembuatan ulang ringkasan. */
    public function buatUlangRingkasan(int $documentId): array
    {
        $respons = $this->permintaan((int) config('pustakaai.ai.timeout'))
            ->post('/summarize', ['document_id' => $documentId]);

        if ($respons->failed()) {
            throw new RuntimeException(
                'Gagal membuat ulang ringkasan: '.$this->pesanGalat($respons->json(), $respons->body())
            );
        }

        return $respons->json();
    }

    /**
     * NFR 6.4: dipakai antarmuka untuk menampilkan mode degradasi
     * ketika layanan AI sedang tidak tersedia.
     */
    public function sehat(): bool
    {
        try {
            return Http::baseUrl($this->basis())
                ->timeout(3)
                ->get('/health')
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function pesanGalat(mixed $json, string $mentah): string
    {
        if (is_array($json)) {
            return (string) ($json['detail'] ?? $json['message'] ?? $mentah);
        }

        return $mentah !== '' ? $mentah : 'layanan tidak dapat dihubungi';
    }
}
