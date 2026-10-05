<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asisten mengambang hanya menemani penelusuran katalog. Di halaman
 * pengelolaan (Administrasi, Kelola Dokumen) admin sedang mengurus data,
 * bukan mencari bacaan, sehingga tombolnya justru menghalangi.
 */
class AsistenTest extends TestCase
{
    use RefreshDatabase;

    private const PENANDA = 'asisten-mengambang';

    private function dokumen(): Document
    {
        return Document::create([
            'title' => 'Dokumen Uji Asisten',
            'classification' => Document::KLASIFIKASI_PUBLIK,
            'ai_allowed' => true,
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 1024,
            'status' => Document::STATUS_READY,
        ]);
    }

    public function test_tampil_di_halaman_katalog(): void
    {
        $dokumen = $this->dokumen();
        $admin = $this->buatPengguna(User::ROLE_ADMIN);

        foreach ([
            route('katalog.index'),
            route('katalog.semua'),
            route('katalog.show', $dokumen),
        ] as $alamat) {
            $this->actingAs($admin)->get($alamat)->assertOk()->assertSee(self::PENANDA);
        }
    }

    public function test_tidak_tampil_di_halaman_pengelolaan(): void
    {
        $admin = $this->buatPengguna(User::ROLE_ADMIN);

        foreach ([
            route('dokumen.index'),
            route('dokumen.create'),
            route('admin.dasbor'),
            route('admin.kategori.index'),
            route('admin.pengguna'),
        ] as $alamat) {
            $this->actingAs($admin)->get($alamat)->assertOk()->assertDontSee(self::PENANDA);
        }
    }

    /** Pembaca dokumen sengaja dibuat bersih tanpa elemen menempel. */
    public function test_tidak_tampil_di_pembaca_dokumen(): void
    {
        $dokumen = $this->dokumen();

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('pembaca.show', $dokumen))
            ->assertOk()
            ->assertDontSee(self::PENANDA);
    }

    /** Tamu tidak punya asisten sama sekali -- kuota AI dijaga. */
    public function test_tidak_tampil_bagi_tamu(): void
    {
        $this->dokumen();

        $this->tamuTerdaftar()
            ->get(route('publik.katalog.index'))
            ->assertOk()
            ->assertDontSee(self::PENANDA);
    }
}
