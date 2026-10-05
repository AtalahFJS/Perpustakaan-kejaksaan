<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buku tamu (pembaruan 26 Agustus 2026).
 *
 * Pengunjung mengisi identitas singkat sebelum masuk katalog, sehingga
 * lembaga punya catatan siapa saja yang mengakses aplikasi ini. Nomor
 * telepon dan domisili adalah data pribadi, jadi hanya admin yang dapat
 * melihat isinya -- lihat rute admin.buku-tamu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_books', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('no_telp', 32);
            $table->string('domisili');

            // Dicatat apa adanya untuk keperluan audit akses (F-7.2).
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Laporan buku tamu selalu dibaca dari yang terbaru.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_books');
    }
};
