<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan 26 Agustus 2026 (permintaan pengguna).
 *
 * 1. Akses dokumen kembali ditentukan per dokumen saat unggah, bukan
 *    diturunkan dari kategori. Kategori kembali menjadi pengelompokan
 *    murni, sehingga kolom `visibility`-nya dibuang. Kolom
 *    `documents.classification` tetap ada dan kini kembali dipilih manual;
 *    nilainya tidak diubah sama sekali oleh migrasi ini supaya tidak ada
 *    dokumen yang berpindah akses tanpa sepengetahuan admin.
 *
 * 2. Kategori mendapat kolom `urutan` agar admin dapat menyusun sendiri
 *    tampilannya di katalog. Nilai awal mengikuti urutan abjad yang selama
 *    ini dipakai, jadi tampilannya tidak berubah sampai admin menyusunnya.
 *
 * 3. Dokumen mendapat `tanggal_terbit` agar "Dokumen Terbaru" dapat
 *    diurutkan menurut tanggal rilis, bukan waktu unggah. Dokumen lama
 *    diisi perkiraan 1 Januari pada tahunnya -- ketelitiannya sebatas
 *    tahun, tetapi urutannya langsung benar tanpa admin mengisi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->date('tanggal_terbit')->nullable()->after('year');
        });

        // Perkiraan dari kolom tahun yang sudah ada.
        DB::statement("
            UPDATE documents
            SET tanggal_terbit = make_date(year, 1, 1)
            WHERE year IS NOT NULL AND tanggal_terbit IS NULL
        ");

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('urutan')->default(0)->after('slug');
        });

        // Urutan awal = abjad, meniru tampilan sebelum kolom ini ada.
        DB::statement("
            UPDATE categories AS c
            SET urutan = t.baris
            FROM (SELECT id, row_number() OVER (ORDER BY name) AS baris FROM categories) AS t
            WHERE c.id = t.id
        ");

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('visibility', 20)->default('internal');
        });

        // Visibilitas kategori dipulihkan sebagai perkiraan: kategori
        // dianggap publik bila memuat dokumen publik. Pemetaan aslinya
        // tidak tersimpan, jadi ini tidak dapat dikembalikan persis.
        DB::statement("
            UPDATE categories AS c SET visibility = 'publik'
            WHERE EXISTS (
                SELECT 1 FROM documents d
                WHERE d.category_id = c.id AND d.classification = 'publik'
            )
        ");

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('tanggal_terbit');
        });
    }
};
