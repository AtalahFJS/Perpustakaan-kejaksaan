<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan berdasarkan hasil wawancara (21 Agustus 2026):
 *
 * - Katalog kini punya jalur akses publik tanpa login. Kategori perlu
 *   menyatakan sendiri apakah boleh dilihat pengunjung umum atau hanya
 *   pegawai yang sudah masuk (setara "internal" pada klasifikasi dokumen).
 * - Peran "Pustakawan" dan "Pembaca" digabung menjadi satu peran baru
 *   "Pegawai" -- pengguna internal yang sifatnya seperti pembaca biasa,
 *   hanya saja dapat melihat kategori/dokumen internal. Kewenangan
 *   mengelola konten (unggah, ubah metadata, tinjau ringkasan AI)
 *   sepenuhnya berpindah ke Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Sejalan dengan documents.classification: publik tampil ke
            // siapa saja termasuk pengunjung tanpa login; internal hanya
            // untuk pengguna yang sudah masuk (pegawai/admin).
            $table->string('visibility', 20)->default('publik')->after('parent_id');
        });

        // Data lama: seluruh kategori yang sudah ada dianggap publik agar
        // tidak ada yang tiba-tiba hilang dari katalog publik pasca-migrasi.
        DB::table('categories')->update(['visibility' => 'publik']);

        // Pustakawan dan Pembaca melebur menjadi Pegawai. Baik akun
        // pustakawan (dulu mengelola konten) maupun pembaca (dulu hanya
        // membaca) kini punya hak lihat yang sama: publik + internal,
        // tanpa kewenangan mengelola konten -- itu sepenuhnya jadi milik
        // Admin.
        DB::table('users')
            ->whereIn('role', ['pustakawan', 'pembaca'])
            ->update(['role' => 'pegawai']);

        // Nilai bawaan kolom juga disesuaikan agar akun baru yang dibuat
        // tanpa peran eksplisit tidak jatuh ke peran "pembaca" yang sudah
        // tidak ada lagi.
        DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'pegawai'");
    }

    public function down(): void
    {
        // Tidak dapat menentukan lagi mana akun yang dulunya pustakawan
        // vs pembaca -- kembalikan semuanya sebagai pembaca (peran dengan
        // kewenangan paling rendah) agar tidak ada yang tiba-tiba
        // mendapat akses admin/pustakawan saat rollback.
        DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'pembaca'");
        DB::table('users')->where('role', 'pegawai')->update(['role' => 'pembaca']);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
