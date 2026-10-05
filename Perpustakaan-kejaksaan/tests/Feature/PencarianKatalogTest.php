<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F-3.1 & F-3.2: satu kotak "Kata kunci" harus menemukan dokumen lewat
 * judul, penulis, maupun tag -- tanpa kolom pencarian terpisah.
 *
 * Tag sengaja diuji tersendiri karena tersimpan di tabel terpisah dan
 * TIDAK dapat ikut ke dalam kolom generated `search_vector`; kalau klausa
 * khususnya hilang, tag akan diam-diam berhenti dapat dicari.
 */
class PencarianKatalogTest extends TestCase
{
    use RefreshDatabase;

    private function buatDokumen(array $tambahan = [], array $tag = []): Document
    {
        $kategori = Category::firstOrCreate(
            ['slug' => 'publik-uji'],
            ['name' => 'Publik Uji'],
        );

        $dokumen = Document::create(array_merge([
            'title' => 'Dokumen Tanpa Nama',
            'classification' => Document::KLASIFIKASI_PUBLIK,
            'ai_allowed' => true,
            'category_id' => $kategori->id,
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 1024,
            'status' => Document::STATUS_READY,
        ], $tambahan));

        foreach ($tag as $nama) {
            $dokumen->tags()->attach(
                Tag::firstOrCreate(['slug' => str($nama)->slug()], ['name' => $nama])->id
            );
        }

        return $dokumen;
    }

    /** @return array<string, array{string, string}> */
    public static function kataKunci(): array
    {
        return [
            'judul' => ['Kepailitan', 'Pedoman Kepailitan Negara'],
            'penulis' => ['Wirawan', 'Dr. Wirawan Adhyaksa'],
            'tag' => ['tipikor', 'Dokumen Bertag'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kataKunci')]
    public function test_pencarian_pegawai_menemukan_lewat_judul_penulis_dan_tag(string $kata, string $judulDicari): void
    {
        $this->buatDokumen(['title' => 'Pedoman Kepailitan Negara']);
        $this->buatDokumen(['title' => 'Kajian Hukum Acara', 'author' => 'Dr. Wirawan Adhyaksa']);
        $this->buatDokumen(['title' => 'Dokumen Bertag'], ['tipikor']);

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('katalog.semua', ['q' => $kata]))
            ->assertOk()
            ->assertSee($judulDicari);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kataKunci')]
    public function test_pencarian_publik_menemukan_lewat_judul_penulis_dan_tag(string $kata, string $judulDicari): void
    {
        $this->buatDokumen(['title' => 'Pedoman Kepailitan Negara']);
        $this->buatDokumen(['title' => 'Kajian Hukum Acara', 'author' => 'Dr. Wirawan Adhyaksa']);
        $this->buatDokumen(['title' => 'Dokumen Bertag'], ['tipikor']);

        $this->tamuTerdaftar()->get(route('publik.katalog.semua', ['q' => $kata]))
            ->assertOk()
            ->assertSee($judulDicari);
    }

    /**
     * Abstrak sengaja TIDAK dicari: isinya panjang dan naratif, sehingga
     * satu kata umum di dalamnya akan menarik dokumen yang tidak relevan.
     */
    public function test_abstrak_tidak_ikut_dicari(): void
    {
        $this->buatDokumen([
            'title' => 'Dokumen Berabstrak',
            'abstract' => 'Kajian mengenai restrukturisasi kelembagaan di daerah.',
        ]);

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('katalog.semua', ['q' => 'restrukturisasi']))
            ->assertOk()
            ->assertDontSee('Dokumen Berabstrak');
    }

    /**
     * Isi dokumen (tabel chunks) juga tidak dicari. Chunks tetap ada untuk
     * tanya-jawab AI -- yang berhenti hanyalah perannya di pencarian katalog.
     */
    public function test_isi_dokumen_tidak_ikut_dicari(): void
    {
        $dokumen = $this->buatDokumen(['title' => 'Dokumen Berisi']);
        $dokumen->chunks()->create([
            'chunk_index' => 0,
            'content' => 'Pasal ini mengatur tentang kepailitan badan usaha.',
            'page_start' => 1,
            'page_end' => 1,
        ]);

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('katalog.semua', ['q' => 'kepailitan']))
            ->assertOk()
            ->assertDontSee('Dokumen Berisi');

        // Potongannya tetap ada -- hanya tidak dipakai untuk pencarian.
        $this->assertSame(1, $dokumen->chunks()->count());
    }

    /** Nomor dokumen tetap dapat dicari karena spesifik. */
    public function test_nomor_dokumen_masih_dapat_dicari(): void
    {
        $this->buatDokumen([
            'title' => 'Dokumen Bernomor',
            'document_number' => 'PERJA-12/A/JA/05/2026',
        ]);

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('katalog.semua', ['q' => 'PERJA-12']))
            ->assertOk()
            ->assertSee('Dokumen Bernomor');
    }

    /** Kata kunci yang tidak cocok tidak boleh menarik dokumen lain. */
    public function test_kata_kunci_tak_cocok_tidak_mengembalikan_apa_apa(): void
    {
        $this->buatDokumen(['title' => 'Pedoman Kepailitan Negara'], ['tipikor']);

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('katalog.semua', ['q' => 'kataYangTidakAdaSamaSekali']))
            ->assertOk()
            ->assertSee('Tidak ada dokumen yang cocok.');
    }

    /** Pencarian tag tetap tunduk pada batas akses -- bukan pintu belakang. */
    public function test_pencarian_tag_tidak_membocorkan_dokumen_internal_ke_publik(): void
    {
        $internal = Category::create([
            'name' => 'Internal Uji',
            'slug' => 'internal-uji',
        ]);

        $this->buatDokumen([
            'title' => 'Rahasia Dapur',
            'classification' => Document::KLASIFIKASI_INTERNAL,
            'category_id' => $internal->id,
        ], ['tipikor']);

        $this->tamuTerdaftar()->get(route('publik.katalog.semua', ['q' => 'tipikor']))
            ->assertOk()
            ->assertDontSee('Rahasia Dapur');
    }
}
