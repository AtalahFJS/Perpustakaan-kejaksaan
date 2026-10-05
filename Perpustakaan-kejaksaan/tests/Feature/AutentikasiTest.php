<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modul autentikasi dan otorisasi (PRD 5.1).
 */
class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    /** F-1.1: masuk dengan nama pengguna. */
    public function test_pengguna_dapat_masuk_dengan_nama_pengguna(): void
    {
        $pengguna = $this->buatPengguna();

        $this->post(route('masuk'), [
            'username' => $pengguna->username,
            'password' => 'rahasia123',
        ])->assertRedirect(route('katalog.index'));

        $this->assertAuthenticatedAs($pengguna);
    }

    /** F-1.1: masuk juga dapat memakai email. */
    public function test_pengguna_dapat_masuk_dengan_email(): void
    {
        $pengguna = $this->buatPengguna();

        $this->post(route('masuk'), [
            'username' => $pengguna->email,
            'password' => 'rahasia123',
        ])->assertRedirect(route('katalog.index'));

        $this->assertAuthenticatedAs($pengguna);
    }

    /** F-1.4: akun nonaktif tidak dapat masuk. */
    public function test_akun_nonaktif_tidak_dapat_masuk(): void
    {
        $pengguna = $this->buatPengguna(User::ROLE_PEGAWAI, aktif: false);

        $this->post(route('masuk'), [
            'username' => $pengguna->username,
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    /** F-1.4: akun yang dinonaktifkan saat sesi berjalan langsung dikeluarkan. */
    public function test_akun_yang_dinonaktifkan_dikeluarkan_dari_sesi(): void
    {
        $pengguna = $this->buatPengguna();
        $this->actingAs($pengguna)->get(route('katalog.index'))->assertOk();

        $pengguna->update(['is_active' => false]);

        $this->get(route('katalog.index'))->assertRedirect(route('masuk'));
        $this->assertGuest();
    }

    /** F-1.5: setiap percobaan masuk dicatat, berhasil maupun gagal. */
    public function test_percobaan_masuk_dicatat(): void
    {
        $pengguna = $this->buatPengguna();

        $this->post(route('masuk'), [
            'username' => $pengguna->username,
            'password' => 'salah',
        ]);

        $this->assertSame(1, LoginHistory::where('berhasil', false)->count());

        $this->post(route('masuk'), [
            'username' => $pengguna->username,
            'password' => 'rahasia123',
        ]);

        $this->assertSame(1, LoginHistory::where('berhasil', true)->count());
    }

    /** F-1.2: pegawai bukan pengelola konten, jadi tidak boleh membuka halaman kelola dokumen. */
    public function test_pegawai_ditolak_masuk_halaman_kelola_dokumen(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('dokumen.index'))
            ->assertForbidden();
    }

    /** F-1.2: pegawai bukan admin, jadi tidak boleh membuka administrasi. */
    public function test_pegawai_ditolak_masuk_halaman_administrasi(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('admin.dasbor'))
            ->assertForbidden();
    }

    public function test_admin_dapat_membuka_halaman_administrasi(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->get(route('admin.dasbor'))
            ->assertOk();
    }

    /** Admin tidak boleh mengunci dirinya sendiri di luar sistem. */
    public function test_admin_tidak_dapat_menonaktifkan_akunnya_sendiri(): void
    {
        $admin = $this->buatPengguna(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->put(route('admin.pengguna.ubah', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => User::ROLE_ADMIN,
                'is_active' => 0,
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->is_active);
    }

    /** NFR 6.2: kata sandi disimpan dengan hashing modern, bukan teks polos. */
    public function test_kata_sandi_disimpan_dalam_bentuk_hash(): void
    {
        $pengguna = $this->buatPengguna();

        $this->assertNotSame('rahasia123', $pengguna->password);
        $this->assertTrue(str_starts_with($pengguna->password, '$2y$'));
    }
}
