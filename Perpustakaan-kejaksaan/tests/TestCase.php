<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Melewati gerbang buku tamu, untuk uji yang menyasar katalog publik
     * dan bukan gerbangnya sendiri.
     */
    protected function tamuTerdaftar(): static
    {
        $tamu = \App\Models\BukuTamu::create([
            'nama' => 'Tamu Uji',
            'no_telp' => '081234567890',
            'domisili' => 'Bandung',
        ]);

        $this->withSession([\App\Models\BukuTamu::KUNCI_SESI => $tamu->id]);

        return $this;
    }

    protected function buatPengguna(string $peran = User::ROLE_PEGAWAI, bool $aktif = true): User
    {
        static $urutan = 0;
        $urutan++;

        return User::create([
            'name' => 'Pengguna Uji '.$urutan,
            'username' => 'uji'.$urutan,
            'email' => "uji{$urutan}@contoh.test",
            'password' => 'rahasia123',
            'role' => $peran,
            'is_active' => $aktif,
        ]);
    }
}
