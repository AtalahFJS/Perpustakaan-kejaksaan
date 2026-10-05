<?php

use App\Models\Category;
use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan 23 Agustus 2026 (hasil peninjauan pengguna).
 *
 * Sebelumnya kolom `classification` merangkap dua urusan yang berbeda:
 * siapa yang boleh melihat dokumen, dan apakah isinya boleh diproses AI.
 * Keduanya kini dipisah:
 *
 *   - Siapa yang boleh melihat  -> ditentukan visibilitas kategori.
 *     `classification` tetap ada sebagai salinan visibilitas kategori pada
 *     dokumen (dipakai indeks & penyaringan katalog), tapi tidak lagi
 *     dipilih manual oleh admin -- selalu diturunkan dari kategorinya.
 *   - Boleh diproses AI         -> kolom baru `ai_allowed`.
 *
 * Tingkat "terbatas" (hanya admin) ditiadakan atas keputusan pengguna:
 * dokumen kini hanya publik atau internal, mengikuti kategorinya.
 *
 * Hierarki kategori (`parent_id`) juga ditiadakan. Kolomnya dipertahankan
 * agar migrasi dapat dibalik, tetapi seluruh nilainya dikosongkan -- ini
 * sekaligus memperbaiki kategori anak yang selama ini tidak pernah muncul
 * di "Telusuri per Kategori" karena disaring whereNull('parent_id').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('ai_allowed')->default(true)->after('classification');
        });

        // Dokumen terbatas selama ini berarti "tidak pernah diproses AI".
        DB::table('documents')
            ->where('classification', 'terbatas')
            ->update(['ai_allowed' => false]);

        // Tingkat "terbatas" dihapus: dokumennya turun mengikuti visibilitas
        // kategori masing-masing, dan tanpa kategori dianggap internal --
        // pilihan yang lebih tertutup, supaya tidak ada dokumen yang
        // tiba-tiba terbuka ke publik akibat migrasi ini.
        DB::table('documents')
            ->where('classification', 'terbatas')
            ->update(['classification' => 'internal']);

        // Samakan seluruh dokumen dengan visibilitas kategorinya.
        foreach (Category::query()->get(['id', 'visibility']) as $kategori) {
            DB::table('documents')
                ->where('category_id', $kategori->id)
                ->update(['classification' => $kategori->visibility]);
        }

        DB::table('documents')
            ->whereNull('category_id')
            ->update(['classification' => Document::KLASIFIKASI_INTERNAL]);

        // Ratakan hierarki kategori.
        DB::table('categories')->whereNotNull('parent_id')->update(['parent_id' => null]);
    }

    public function down(): void
    {
        // Hierarki kategori tidak dapat dipulihkan -- relasi induknya sudah
        // hilang. Yang dapat dikembalikan hanya kolom izin AI.
        DB::table('documents')
            ->where('ai_allowed', false)
            ->update(['classification' => 'terbatas']);

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('ai_allowed');
        });
    }
};
