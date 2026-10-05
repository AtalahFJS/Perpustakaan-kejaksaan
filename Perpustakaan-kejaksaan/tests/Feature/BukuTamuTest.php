<?php

namespace Tests\Feature;

use App\Models\BukuTamu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gerbang sebelum katalog: pengunjung memilih Tamu atau Pegawai, dan tamu
 * mencatatkan identitasnya lebih dulu (pembaruan 26 Agustus 2026).
 */
class BukuTamuTest extends TestCase
{
    use RefreshDatabase;

    private array $isian = [
        'nama' => 'Siti Rahayu',
        'no_telp' => '0812-3456-7890',
        'domisili' => 'Kabupaten Bandung',
    ];

    /** Tanpa mengisi buku tamu, katalog publik belum boleh dibuka. */
    public function test_katalog_publik_dijaga_sampai_buku_tamu_diisi(): void
    {
        $this->get(route('publik.katalog.index'))->assertRedirect(route('gerbang'));
    }

    /** Tautan langsung ke dokumen bukan jalan pintas melewati pencatatan. */
    public function test_tautan_langsung_ke_dokumen_juga_dijaga(): void
    {
        // Dokumennya harus benar-benar ada: pengikatan model rute berjalan
        // sebelum middleware, jadi dokumen fiktif akan 404 lebih dulu dan
        // penjaganya tidak pernah teruji.
        $dokumen = \App\Models\Document::create([
            'title' => 'Dokumen Terbuka',
            'classification' => \App\Models\Document::KLASIFIKASI_PUBLIK,
            'ai_allowed' => true,
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 1024,
            'status' => \App\Models\Document::STATUS_READY,
        ]);

        $this->get(route('publik.katalog.show', $dokumen))->assertRedirect(route('gerbang'));
    }

    public function test_mengisi_buku_tamu_membuka_katalog_dan_tercatat(): void
    {
        $this->post(route('buku-tamu.simpan'), $this->isian)
            ->assertRedirect(route('publik.katalog.index'));

        $this->assertDatabaseHas('guest_books', [
            'nama' => 'Siti Rahayu',
            'domisili' => 'Kabupaten Bandung',
        ]);

        // Sesi menandai tamu ini, jadi kunjungan berikutnya lolos gerbang.
        $this->assertNotNull(session(BukuTamu::KUNCI_SESI));
        $this->get(route('publik.katalog.index'))->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function kolomWajib(): array
    {
        return ['nama' => ['nama'], 'no_telp' => ['no_telp'], 'domisili' => ['domisili']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kolomWajib')]
    public function test_seluruh_kolom_wajib_diisi(string $kolom): void
    {
        $this->post(route('buku-tamu.simpan'), [...$this->isian, $kolom => ''])
            ->assertSessionHasErrors($kolom);

        $this->assertDatabaseCount('guest_books', 0);
    }

    public function test_nomor_telepon_yang_jelas_bukan_nomor_ditolak(): void
    {
        $this->post(route('buku-tamu.simpan'), [...$this->isian, 'no_telp' => 'bukan nomor'])
            ->assertSessionHasErrors('no_telp');
    }

    /** Pengguna yang sudah masuk tidak perlu melewati gerbang. */
    public function test_pengguna_masuk_langsung_ke_katalog_internal(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get('/')
            ->assertRedirect(route('katalog.index'));
    }

    /** F-7.2: daftar buku tamu memuat data pribadi -- khusus admin. */
    public function test_daftar_buku_tamu_hanya_untuk_admin(): void
    {
        BukuTamu::create($this->isian);

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('admin.buku-tamu'))
            ->assertForbidden();

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->get(route('admin.buku-tamu'))
            ->assertOk()
            ->assertSee('Siti Rahayu');
    }
}
