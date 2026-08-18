<?php

namespace Tests\Feature;

use App\Jobs\ProsesIngestDokumen;
use App\Models\Document;
use App\Models\User;
use App\Services\KlienLayananAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Menguji aturan klasifikasi dan kerahasiaan dokumen -- PRD 5.8.
 * Ini bagian paling sensitif dari sistem, karena menyangkut apakah isi
 * dokumen rahasia dapat bocor ke layanan pihak ketiga (risiko R1).
 */
class KlasifikasiDokumenTest extends TestCase
{
    use RefreshDatabase;

    private function buatDokumen(string $klasifikasi, array $tambahan = []): Document
    {
        return Document::create(array_merge([
            'title' => 'Dokumen '.$klasifikasi,
            'classification' => $klasifikasi,
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 1024,
            'status' => Document::STATUS_READY,
        ], $tambahan));
    }

    /** F-8.4: pembaca tidak boleh melihat dokumen terbatas di katalog. */
    public function test_pembaca_tidak_melihat_dokumen_terbatas_di_katalog(): void
    {
        $this->buatDokumen(Document::KLASIFIKASI_PUBLIK, ['title' => 'Dokumen Publik']);
        $this->buatDokumen(Document::KLASIFIKASI_TERBATAS, ['title' => 'Dokumen Terbatas']);

        $respons = $this->actingAs($this->buatPengguna(User::ROLE_PEMBACA))
            ->get(route('katalog.index'));

        $respons->assertOk();
        $respons->assertSee('Dokumen Publik');
        $respons->assertDontSee('Dokumen Terbatas');
    }

    /** F-8.4: pustakawan melihat seluruh klasifikasi. */
    public function test_pustakawan_melihat_dokumen_terbatas(): void
    {
        $this->buatDokumen(Document::KLASIFIKASI_TERBATAS, ['title' => 'Dokumen Terbatas']);

        $this->actingAs($this->buatPengguna(User::ROLE_PUSTAKAWAN))
            ->get(route('katalog.index'))
            ->assertOk()
            ->assertSee('Dokumen Terbatas');
    }

    /** F-8.4: akses langsung ke detail dokumen terbatas ditolak. */
    public function test_pembaca_ditolak_membuka_detail_dokumen_terbatas(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_TERBATAS);

        $this->actingAs($this->buatPengguna(User::ROLE_PEMBACA))
            ->get(route('katalog.show', $dokumen))
            ->assertForbidden();
    }

    /** F-4.3: berkas hanya dapat diambil oleh pengguna yang berhak. */
    public function test_berkas_dokumen_terbatas_tidak_dapat_diambil_pembaca(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_TERBATAS);

        $this->actingAs($this->buatPengguna(User::ROLE_PEMBACA))
            ->get(route('berkas.lihat', $dokumen))
            ->assertForbidden();
    }

    /** F-4.3: tamu tidak dapat mengakses berkas sama sekali. */
    public function test_tamu_tidak_dapat_mengakses_berkas(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_PUBLIK);

        $this->get(route('berkas.lihat', $dokumen))
            ->assertRedirect(route('masuk'));
    }

    /** F-8.2: dokumen terbatas tidak pernah masuk antrean pemrosesan AI. */
    public function test_dokumen_terbatas_tidak_dimasukkan_ke_antrean_ingest(): void
    {
        Queue::fake();

        $this->actingAs($this->buatPengguna(User::ROLE_PUSTAKAWAN))
            ->post(route('dokumen.store'), [
                'title' => 'Dokumen Rahasia',
                'classification' => Document::KLASIFIKASI_TERBATAS,
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

    /** F-8.2: dokumen internal tetap diproses seperti biasa. */
    public function test_dokumen_internal_dimasukkan_ke_antrean_ingest(): void
    {
        Queue::fake();

        $this->actingAs($this->buatPengguna(User::ROLE_PUSTAKAWAN))
            ->post(route('dokumen.store'), [
                'title' => 'Pedoman Internal',
                'classification' => Document::KLASIFIKASI_INTERNAL,
                'konfirmasi_klasifikasi' => '1',
                'berkas' => $this->berkasPdfPalsu(),
            ]);

        Queue::assertPushed(ProsesIngestDokumen::class);
    }

    /**
     * F-8.2: pengaman lapis kedua -- bila klasifikasi berubah menjadi
     * terbatas setelah pekerjaan masuk antrean, pekerjaan tetap menolak
     * memanggil layanan AI.
     */
    public function test_pekerjaan_ingest_menolak_dokumen_terbatas(): void
    {
        $dokumen = $this->buatDokumen(Document::KLASIFIKASI_TERBATAS, [
            'status' => Document::STATUS_UPLOADED,
        ]);

        $ai = Mockery::mock(KlienLayananAi::class);
        $ai->shouldNotReceive('ingest');

        (new ProsesIngestDokumen($dokumen->id))->handle($ai);

        $this->assertSame(Document::STATUS_KATALOG_SAJA, $dokumen->fresh()->status);
    }

    /** F-8.3: klasifikasi wajib dikonfirmasi sebelum dokumen tersimpan. */
    public function test_unggahan_ditolak_tanpa_konfirmasi_klasifikasi(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_PUSTAKAWAN))
            ->post(route('dokumen.store'), [
                'title' => 'Tanpa Konfirmasi',
                'classification' => Document::KLASIFIKASI_PUBLIK,
                'berkas' => $this->berkasPdfPalsu(),
            ])
            ->assertSessionHasErrors('konfirmasi_klasifikasi');

        $this->assertDatabaseCount('documents', 0);
    }

    /**
     * Mengubah dokumen menjadi terbatas harus membuang seluruh turunan AI
     * yang sudah dibuat, supaya isinya tidak lagi dapat ditanyakan.
     */
    public function test_mengubah_menjadi_terbatas_menghapus_potongan_dan_ringkasan(): void
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

        $this->actingAs($this->buatPengguna(User::ROLE_PUSTAKAWAN))
            ->put(route('dokumen.update', $dokumen), [
                'title' => $dokumen->title,
                'classification' => Document::KLASIFIKASI_TERBATAS,
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
