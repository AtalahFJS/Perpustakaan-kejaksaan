<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F-8.4 (revisi 21 Agustus 2026, hasil wawancara): user umum benar-benar
 * publik tanpa login. Hanya dokumen berklasifikasi publik DALAM kategori
 * publik yang boleh terlihat pengunjung anonim.
 */
class AksesPublikTest extends TestCase
{
    use RefreshDatabase;

    private function buatDokumen(string $klasifikasi, ?Category $kategori = null, array $tambahan = []): Document
    {
        return Document::create(array_merge([
            'title' => 'Dokumen '.$klasifikasi.' '.uniqid(),
            'classification' => $klasifikasi,
            'category_id' => $kategori?->id,
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 1024,
            'status' => Document::STATUS_READY,
        ], $tambahan));
    }

    public function test_tamu_hanya_melihat_dokumen_publik_di_katalog_publik(): void
    {
        // Satu kategori untuk keduanya: sejak 26 Agustus 2026 yang menentukan
        // akses adalah dokumennya sendiri, kategori tidak ikut menggerbangi.
        $kategori = Category::create(['name' => 'Campuran', 'slug' => 'campuran-uji']);

        $this->buatDokumen(Document::KLASIFIKASI_PUBLIK, $kategori, ['title' => 'Dokumen Terbuka']);
        $this->buatDokumen(Document::KLASIFIKASI_INTERNAL, $kategori, ['title' => 'Dokumen Internal']);

        $respons = $this->tamuTerdaftar()->get(route('publik.katalog.index'));

        $respons->assertOk();
        $respons->assertSee('Dokumen Terbuka');
        $respons->assertDontSee('Dokumen Internal');
    }

    public function test_tamu_ditolak_membuka_detail_dokumen_non_publik(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_INTERNAL);

        $this->tamuTerdaftar()->get(route('publik.katalog.show', $dokumen))->assertNotFound();
    }

    public function test_tamu_dapat_membuka_detail_dan_membaca_dokumen_publik(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_PUBLIK);

        $this->tamuTerdaftar()->get(route('publik.katalog.show', $dokumen))->assertOk();
        $this->tamuTerdaftar()->get(route('publik.pembaca.show', $dokumen))->assertOk();
    }

    public function test_tamu_ditolak_mengambil_berkas_dokumen_non_publik(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_INTERNAL);

        $this->tamuTerdaftar()->get(route('publik.berkas.lihat', $dokumen))->assertNotFound();
    }

    /** Katalog internal (/katalog) tetap mewajibkan login walau katalog publik tidak. */
    public function test_tamu_tidak_dapat_membuka_katalog_internal(): void
    {
        $this->get(route('katalog.index'))->assertRedirect(route('masuk'));
    }

    /** Fitur tanya-jawab AI sengaja tidak tersedia bagi pengunjung anonim. */
    public function test_tidak_ada_rute_chat_publik(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_PUBLIK, null, ['status' => Document::STATUS_READY]);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('publik.chat.tanya'));
        // Rute chat asli tetap di balik login.
        $this->get(route('chat.show', $dokumen))->assertRedirect(route('masuk'));
    }
}
