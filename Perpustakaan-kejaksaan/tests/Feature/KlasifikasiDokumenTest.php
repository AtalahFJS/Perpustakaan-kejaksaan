<?php

namespace Tests\Feature;

use App\Jobs\ProsesIngestDokumen;
use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use App\Services\KlienLayananAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Menguji aturan akses dan kerahasiaan dokumen -- PRD 5.8. Ini bagian
 * paling sensitif dari sistem, karena menyangkut apakah isi dokumen
 * rahasia dapat bocor ke layanan pihak ketiga (risiko R1).
 *
 * Pembaruan 26 Agustus 2026: dua urusan yang dulu menyatu tetap terpisah,
 * tetapi keduanya kini dipilih per dokumen saat unggah.
 *   - Siapa yang boleh melihat -> kolom classification (Publik/Internal).
 *   - Boleh diproses AI        -> kolom ai_allowed.
 * Kategori tidak lagi ikut menentukan akses.
 */
class KlasifikasiDokumenTest extends TestCase
{
    use RefreshDatabase;

    private function buatDokumen(string $akses, array $tambahan = []): Document
    {
        return Document::create(array_merge([
            'title' => 'Dokumen '.$akses,
            'classification' => $akses,
            'ai_allowed' => true,
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 1024,
            'status' => Document::STATUS_READY,
        ], $tambahan));
    }

    // --- Akses: ditentukan per dokumen ------------------------------------

    /** F-8.4: dokumen internal tidak terlihat tanpa login. */
    public function test_pengunjung_tidak_melihat_dokumen_internal(): void
    {
        // Sengaja SATU kategori untuk keduanya: membuktikan yang menentukan
        // akses adalah dokumennya sendiri, bukan kategorinya.
        $kategori = Category::create(['name' => 'Campuran', 'slug' => 'campuran']);

        $this->buatDokumen(Document::KLASIFIKASI_PUBLIK, ['title' => 'Dokumen Terbuka', 'category_id' => $kategori->id]);
        $this->buatDokumen(Document::KLASIFIKASI_INTERNAL, ['title' => 'Dokumen Dalaman', 'category_id' => $kategori->id]);

        $this->tamuTerdaftar()->get(route('publik.katalog.index'))
            ->assertOk()
            ->assertSee('Dokumen Terbuka')
            ->assertDontSee('Dokumen Dalaman');
    }

    /** F-8.4: pegawai melihat dokumen internal, sama seperti admin. */
    public function test_pegawai_melihat_dokumen_internal(): void
    {
        $this->buatDokumen(Document::KLASIFIKASI_INTERNAL, ['title' => 'Dokumen Dalaman']);

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('katalog.index'))
            ->assertOk()
            ->assertSee('Dokumen Dalaman');
    }

    /** Akses dipilih manual saat unggah, apa pun kategorinya. */
    public function test_akses_dipilih_manual_saat_unggah(): void
    {
        Queue::fake();
        $kategori = Category::create(['name' => 'Apa Saja', 'slug' => 'apa-saja']);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->post(route('dokumen.store'), [
                'title' => 'Pilihan Sendiri',
                'category_id' => $kategori->id,
                'classification' => Document::KLASIFIKASI_INTERNAL,
                'ai_allowed' => '1',
                'konfirmasi_klasifikasi' => '1',
                'berkas' => $this->berkasPdfPalsu(),
            ])
            ->assertRedirect(route('dokumen.index'));

        $this->assertSame(
            Document::KLASIFIKASI_INTERNAL,
            Document::where('title', 'Pilihan Sendiri')->first()->classification,
        );
    }

    /** Unggahan tanpa pilihan akses ditolak -- tidak ada nilai diam-diam. */
    public function test_unggahan_tanpa_pilihan_akses_ditolak(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->post(route('dokumen.store'), [
                'title' => 'Tanpa Akses',
                'ai_allowed' => '1',
                'konfirmasi_klasifikasi' => '1',
                'berkas' => $this->berkasPdfPalsu(),
            ])
            ->assertSessionHasErrors('classification');

        $this->assertDatabaseCount('documents', 0);
    }

    /** Mengganti nama kategori tidak boleh menyentuh akses dokumennya. */
    public function test_mengubah_kategori_tidak_mengubah_akses_dokumen(): void
    {
        $kategori = Category::create(['name' => 'Berubah', 'slug' => 'b']);
        $this->buatDokumen(Document::KLASIFIKASI_PUBLIK, ['title' => 'Tetap Terbuka', 'category_id' => $kategori->id]);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->put(route('admin.kategori.ubah', $kategori), ['name' => 'Nama Baru'])
            ->assertRedirect();

        $this->assertSame(
            Document::KLASIFIKASI_PUBLIK,
            Document::where('title', 'Tetap Terbuka')->first()->classification,
        );

        $this->tamuTerdaftar()->get(route('publik.katalog.index'))->assertSee('Tetap Terbuka');
    }

    /** F-4.3: tamu tidak dapat mengakses berkas jalur internal sama sekali. */
    public function test_tamu_tidak_dapat_mengakses_berkas(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_PUBLIK);

        $this->get(route('berkas.lihat', $dokumen))
            ->assertRedirect(route('masuk'));
    }

    // --- Pemrosesan AI: ditentukan ai_allowed ------------------------------

    /** F-8.2: dokumen tanpa izin AI tidak pernah masuk antrean pemrosesan. */
    public function test_dokumen_tanpa_izin_ai_tidak_dimasukkan_ke_antrean_ingest(): void
    {
        Queue::fake();

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->post(route('dokumen.store'), [
                'title' => 'Dokumen Rahasia',
                'classification' => Document::KLASIFIKASI_PUBLIK,
                'ai_allowed' => '0',
                'konfirmasi_klasifikasi' => '1',
                'berkas' => $this->berkasPdfPalsu(),
            ])
            ->assertRedirect(route('dokumen.index'));

        Queue::assertNotPushed(ProsesIngestDokumen::class);

        $this->assertSame(
            Document::STATUS_KATALOG_SAJA,
            Document::where('title', 'Dokumen Rahasia')->first()->status,
        );
    }

    /** F-8.2: dokumen berizin AI tetap diproses seperti biasa. */
    public function test_dokumen_berizin_ai_dimasukkan_ke_antrean_ingest(): void
    {
        Queue::fake();

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->post(route('dokumen.store'), [
                'title' => 'Pedoman Internal',
                'classification' => Document::KLASIFIKASI_PUBLIK,
                'ai_allowed' => '1',
                'konfirmasi_klasifikasi' => '1',
                'berkas' => $this->berkasPdfPalsu(),
            ]);

        Queue::assertPushed(ProsesIngestDokumen::class);
    }

    /**
     * F-8.2: pengaman lapis kedua -- bila izin AI dicabut setelah pekerjaan
     * masuk antrean, pekerjaan tetap menolak memanggil layanan AI.
     */
    public function test_pekerjaan_ingest_menolak_dokumen_tanpa_izin_ai(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_INTERNAL, [
            'ai_allowed' => false,
            'status' => Document::STATUS_UPLOADED,
        ]);

        $ai = Mockery::mock(KlienLayananAi::class);
        $ai->shouldNotReceive('ingest');

        (new ProsesIngestDokumen($dokumen->id))->handle($ai);

        $this->assertSame(Document::STATUS_KATALOG_SAJA, $dokumen->fresh()->status);
    }

    /** Dokumen publik pun boleh dilarang diproses AI -- dua urusan terpisah. */
    public function test_dokumen_publik_dapat_dilarang_diproses_ai(): void
    {
        Queue::fake();
        $publik = Category::create(['name' => 'Publik', 'slug' => 'p']);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->post(route('dokumen.store'), [
                'title' => 'Terbuka Tanpa AI',
                'category_id' => $publik->id,
                'classification' => Document::KLASIFIKASI_PUBLIK,
                'ai_allowed' => '0',
                'konfirmasi_klasifikasi' => '1',
                'berkas' => $this->berkasPdfPalsu(),
            ]);

        $dokumen = Document::where('title', 'Terbuka Tanpa AI')->first();

        Queue::assertNotPushed(ProsesIngestDokumen::class);
        $this->assertSame(Document::KLASIFIKASI_PUBLIK, $dokumen->classification);
        $this->assertFalse($dokumen->bolehDiprosesAi());
    }

    /** F-8.3: pilihan pemrosesan AI wajib dikonfirmasi sebelum tersimpan. */
    public function test_unggahan_ditolak_tanpa_konfirmasi(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->post(route('dokumen.store'), [
                'title' => 'Tanpa Konfirmasi',
                'ai_allowed' => '1',
                'berkas' => $this->berkasPdfPalsu(),
            ])
            ->assertSessionHasErrors('konfirmasi_klasifikasi');

        $this->assertDatabaseCount('documents', 0);
    }

    /**
     * Mencabut izin AI harus membuang seluruh turunan AI yang sudah dibuat,
     * supaya isinya tidak lagi dapat ditanyakan.
     */
    public function test_mencabut_izin_ai_menghapus_potongan_dan_ringkasan(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_INTERNAL);
        $dokumen->chunks()->create([
            'chunk_index' => 0,
            'content' => 'isi rahasia',
            'page_start' => 1,
            'page_end' => 1,
        ]);
        $dokumen->summaries()->create([
            'type' => 'singkat',
            'content' => 'ringkasan',
        ]);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->put(route('dokumen.update', $dokumen), [
                'title' => $dokumen->title,
                'classification' => Document::KLASIFIKASI_PUBLIK,
                'ai_allowed' => '0',
                'konfirmasi_klasifikasi' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(0, $dokumen->chunks()->count());
        $this->assertSame(0, $dokumen->summaries()->count());
        $this->assertSame(Document::STATUS_KATALOG_SAJA, $dokumen->fresh()->status);
    }

    private function berkasPdfPalsu(): UploadedFile
    {
        // NFR 6.2: validasi memeriksa isi berkas, jadi berkas uji harus
        // benar-benar diawali angka ajaib PDF.
        $lintasan = tempnam(sys_get_temp_dir(), 'uji').'.pdf';
        file_put_contents($lintasan, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        return new UploadedFile($lintasan, 'uji.pdf', 'application/pdf', null, true);
    }
}
