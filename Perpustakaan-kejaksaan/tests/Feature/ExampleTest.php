<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * F-4.3: seluruh isi perpustakaan berada di balik autentikasi, sehingga
     * tamu yang membuka akar situs diarahkan ke halaman masuk.
     */
    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect(route('katalog.index'));
        $this->get(route('katalog.index'))->assertRedirect(route('masuk'));
    }

    public function test_halaman_masuk_dapat_dibuka(): void
    {
        $this->get(route('masuk'))
            ->assertOk()
            ->assertSee('Masuk');
    }
}
