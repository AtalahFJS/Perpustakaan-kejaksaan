<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pembaruan 24 Agustus 2026 (permintaan pengguna).
 *
 * Pencarian katalog dipersempit agar hasilnya spesifik. Abstrak dikeluarkan
 * dari indeks pencarian karena isinya panjang dan bersifat naratif, sehingga
 * satu kata umum di dalamnya menarik banyak dokumen yang sebenarnya tidak
 * relevan. Yang tersisa hanyalah penanda identitas dokumen: judul, penulis,
 * dan nomor dokumen. Tag dicari lewat klausa tersendiri di
 * Document::scopePencarian() karena tersimpan di tabel terpisah.
 *
 * Pencarian ke isi dokumen (tabel chunks) juga dihentikan, tetapi itu murni
 * perubahan kueri di controller/model -- tabel chunks tetap dipakai untuk
 * tanya-jawab AI, jadi tidak ada yang perlu diubah di sini.
 *
 * Kolom `search_vector` adalah kolom GENERATED; PostgreSQL tidak mengizinkan
 * ekspresinya diubah langsung, jadi kolom beserta indeksnya dibuang lalu
 * dibuat ulang. Nilainya dihitung ulang otomatis untuk seluruh baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->bangunUlangVektor(<<<'SQL'
            setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
            setweight(to_tsvector('simple', coalesce(author, '')), 'B') ||
            setweight(to_tsvector('simple', coalesce(document_number, '')), 'B')
        SQL);
    }

    public function down(): void
    {
        $this->bangunUlangVektor(<<<'SQL'
            setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
            setweight(to_tsvector('simple', coalesce(author, '')), 'B') ||
            setweight(to_tsvector('simple', coalesce(document_number, '')), 'B') ||
            setweight(to_tsvector('simple', coalesce(abstract, '')), 'C')
        SQL);
    }

    private function bangunUlangVektor(string $ekspresi): void
    {
        DB::statement('DROP INDEX IF EXISTS documents_search_vector_idx');
        DB::statement('ALTER TABLE documents DROP COLUMN IF EXISTS search_vector');
        DB::statement("
            ALTER TABLE documents
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS ({$ekspresi}) STORED
        ");
        DB::statement('CREATE INDEX documents_search_vector_idx ON documents USING GIN (search_vector)');
    }
};
