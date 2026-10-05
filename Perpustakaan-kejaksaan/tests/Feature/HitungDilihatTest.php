<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Angka "Dilihat" pada kartu katalog menghitung BERAPA ORANG yang pernah
 * membuka dokumen, bukan berapa kali dibuka.
 *
 * Sebelum 23 Agustus 2026 angkanya diambil dari jumlah baris log audit,
 * sehingga satu orang yang membuka detail, menekan "Baca Dokumen", lalu
 * kembali sudah terhitung dua kali. Log audit sendiri tetap mencatat
 * setiap akses -- itu kebutuhan audit F-7.2 yang terpisah.
 */
class HitungDilihatTest extends TestCase
{
    use RefreshDatabase;

    private function buatDokumenPublik(): Document
    {
        $kategori = Category::create([
            'name' => 'Publik',
            'slug' => 'publik-uji',
        ]);

        return Document::create([
            'title' => 'Dokumen Uji Dilihat',
            'classification' => Document::KLASIFIKASI_PUBLIK,
            'ai_allowed' => true,
            'category_id' => $kategori->id,
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 1024,
            'status' => Document::STATUS_READY,
        ]);
    }

    private function jumlahDilihat(Document $dokumen): int
    {
        return Document::withJumlahDilihat()->find($dokumen->id)->dilihat_count;
    }

    /** Membuka berulang kali oleh orang yang sama tetap terhitung satu. */
    public function test_satu_orang_membuka_berkali_kali_terhitung_sekali(): void
    {
        $dokumen = $this->buatDokumenPublik();
        $pengguna = $this->buatPengguna(User::ROLE_PEGAWAI);

        $this->actingAs($pengguna)->get(route('katalog.show', $dokumen))->assertOk();
        $this->assertSame(1, $this->jumlahDilihat($dokumen));

        // Membuka pembaca lalu kembali ke detail -- inilah yang dulu
        // membuat angkanya berlipat.
        $this->actingAs($pengguna)->get(route('pembaca.show', $dokumen))->assertOk();
        $this->actingAs($pengguna)->get(route('katalog.show', $dokumen))->assertOk();
        $this->actingAs($pengguna)->get(route('katalog.show', $dokumen))->assertOk();

        $this->assertSame(1, $this->jumlahDilihat($dokumen));
    }

    /** Orang yang berbeda tetap dihitung terpisah. */
    public function test_orang_berbeda_dihitung_terpisah(): void
    {
        $dokumen = $this->buatDokumenPublik();

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))->get(route('katalog.show', $dokumen));
        $this->assertSame(1, $this->jumlahDilihat($dokumen));

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))->get(route('katalog.show', $dokumen));
        $this->assertSame(2, $this->jumlahDilihat($dokumen));
    }

    /** Pengunjung tanpa login dibedakan menurut alamat IP. */
    public function test_pengunjung_tanpa_login_dibedakan_menurut_ip(): void
    {
        $dokumen = $this->buatDokumenPublik();

        $this->tamuTerdaftar()->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('publik.katalog.show', $dokumen))->assertOk();
        $this->tamuTerdaftar()->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('publik.katalog.show', $dokumen))->assertOk();

        $this->assertSame(1, $this->jumlahDilihat($dokumen), 'IP yang sama tidak boleh terhitung dua kali.');

        $this->tamuTerdaftar()->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->get(route('publik.katalog.show', $dokumen))->assertOk();

        $this->assertSame(2, $this->jumlahDilihat($dokumen));
    }

    /** F-7.2: log audit tetap mencatat setiap akses, tidak ikut diringkas. */
    public function test_log_audit_tetap_mencatat_setiap_akses(): void
    {
        $dokumen = $this->buatDokumenPublik();
        $pengguna = $this->buatPengguna(User::ROLE_PEGAWAI);

        foreach (range(1, 4) as $_) {
            $this->actingAs($pengguna)->get(route('katalog.show', $dokumen));
        }

        $this->assertSame(4, $dokumen->viewLogs()->count());
        $this->assertSame(1, $this->jumlahDilihat($dokumen));
    }
}
