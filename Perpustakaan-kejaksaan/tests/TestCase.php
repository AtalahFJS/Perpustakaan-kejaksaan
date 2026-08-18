<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function buatPengguna(string $peran = User::ROLE_PEMBACA, bool $aktif = true): User
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
