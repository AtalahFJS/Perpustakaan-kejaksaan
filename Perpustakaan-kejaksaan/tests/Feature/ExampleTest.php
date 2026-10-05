<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sejak pembaruan 21 Agustus 2026: katalog publik dapat diakses tanpa
     * masuk (F-8.4 revisi), tetapi katalog internal (/katalog) tetap
     * mewajibkan autentikasi.
     */
    public function test_tamu_disambut_gerbang_pilihan(): void
    {
        // Sejak 26 Agustus 2026 halaman depan bukan pengalihan, melainkan
        // gerbang: tamu atau pegawai.
        $this->get('/')->assertOk()->assertSee('Tamu')->assertSee('Pegawai');
        $this->get(route('katalog.index'))->assertRedirect(route('masuk'));
    }

    public function test_pengguna_masuk_diarahkan_ke_katalog_internal(): void
    {
        $this->actingAs($this->buatPengguna())
            ->get('/')
            ->assertRedirect(route('katalog.index'));
    }

    public function test_halaman_masuk_dapat_dibuka(): void
    {
        $this->get(route('masuk'))
            ->assertOk()
            ->assertSee('Masuk');
    }
}
