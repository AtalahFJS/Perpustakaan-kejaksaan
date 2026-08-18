<?php

/**
 * Konfigurasi khusus aplikasi PustakaAI.
 * NFR 6.6: penyedia LLM dapat diganti lewat konfigurasi tanpa deploy ulang.
 */
return [

    /*
    |---------------------------------------------------------------------
    | Layanan AI (FastAPI)
    |---------------------------------------------------------------------
    | Seluruh pekerjaan ekstraksi, embedding, dan pemanggilan LLM
    | dilakukan oleh layanan Python terpisah (PRD 7.1).
    */
    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'token' => env('AI_SERVICE_TOKEN', ''),
        // Ingest dokumen tebal butuh menit -- NFR 6.1 menargetkan < 5 menit
        // untuk 300 halaman, jadi batas waktu dibuat longgar.
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 900),
        'timeout_chat' => (int) env('AI_SERVICE_TIMEOUT_CHAT', 120),
    ],

    /*
    |---------------------------------------------------------------------
    | Unggahan berkas
    |---------------------------------------------------------------------
    | F-2.1: maksimum 100 MB. F-2.2: PDF wajib; EPUB dan DOCX menyusul.
    | NFR 6.2: validasi tipe berkas berdasarkan isi, bukan hanya ekstensi.
    */
    'unggah' => [
        'maks_mb' => (int) env('UPLOAD_MAX_MB', 100),
        'mime_diizinkan' => ['application/pdf'],
        'ekstensi_diizinkan' => ['pdf'],
    ],

    /*
    |---------------------------------------------------------------------
    | Kuota pemakaian AI
    |---------------------------------------------------------------------
    | F-7.6: batas kuota pemakaian AI per pengguna per hari.
    */
    'kuota' => [
        'chat_per_hari' => (int) env('AI_QUOTA_CHAT_PER_HARI', 50),
    ],

    /*
    |---------------------------------------------------------------------
    | Katalog
    |---------------------------------------------------------------------
    */
    'katalog' => [
        'per_halaman' => 12,
    ],
];
