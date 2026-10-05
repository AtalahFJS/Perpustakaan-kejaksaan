<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F-8.4 (pembaruan 21 Agustus 2026, hasil wawancara): admin dapat
 * menambah/menghapus kategori dan menentukan visibilitasnya (publik
 * atau internal/pegawai).
 */
class KategoriTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_menambah_kategori(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->post(route('admin.kategori.simpan'), [
                'name' => 'Kategori Baru',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Kategori Baru',
        ]);
    }

    /**
     * "Tambah kategori" ringkas pada formulir unggah dokumen memanggil
     * rute yang sama lewat fetch() dan mengharapkan JSON berisi id serta
     * label siap pakai untuk disisipkan ke menu tarik-turun.
     */
    public function test_tambah_kategori_lewat_json_mengembalikan_kategori_baru(): void
    {
        $jawaban = $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->postJson(route('admin.kategori.simpan'), [
                'name' => 'Surat Edaran',
            ])
            ->assertCreated()
            ->assertJson([
                'name' => 'Surat Edaran',
                // Tanpa keterangan "(publik)/(internal)": kategori tidak lagi
                // menentukan akses, jadi labelnya cukup namanya saja.
                'label' => 'Surat Edaran',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $jawaban->json('id'),
            'name' => 'Surat Edaran',
        ]);
    }

    /** Tambah-cepat tidak menampilkan daftar yang ada, jadi kembar harus ditolak. */
    public function test_kategori_dengan_nama_kembar_ditolak(): void
    {
        Category::create(['name' => 'Surat Edaran', 'slug' => 'surat-edaran']);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->postJson(route('admin.kategori.simpan'), [
                'name' => 'Surat Edaran',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, Category::where('name', 'Surat Edaran')->count());
    }

    /** F-1.2: pegawai tidak boleh menambah kategori, termasuk lewat jalur JSON. */
    public function test_pegawai_ditolak_menambah_kategori_lewat_json(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->postJson(route('admin.kategori.simpan'), [
                'name' => 'Dari Pegawai',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Dari Pegawai']);
    }

    public function test_admin_dapat_mengubah_nama_kategori(): void
    {
        $kategori = Category::create(['name' => 'Uji', 'slug' => 'uji']);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->put(route('admin.kategori.ubah', $kategori), ['name' => 'Uji Baru'])
            ->assertRedirect();

        $this->assertSame('Uji Baru', $kategori->fresh()->name);
    }

    /** Admin menyusun sendiri urutan tampil kategori di katalog. */
    public function test_admin_dapat_menggeser_urutan_kategori(): void
    {
        $pertama = Category::create(['name' => 'A', 'slug' => 'a', 'urutan' => 1]);
        $kedua = Category::create(['name' => 'B', 'slug' => 'b', 'urutan' => 2]);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->put(route('admin.kategori.geser', $kedua), ['arah' => 'naik'])
            ->assertRedirect();

        $this->assertSame(1, $kedua->fresh()->urutan);
        $this->assertSame(2, $pertama->fresh()->urutan);
    }

    /** Menggeser dari ujung tidak boleh mengacak urutan yang lain. */
    public function test_menggeser_dari_ujung_tidak_mengubah_apa_apa(): void
    {
        $pertama = Category::create(['name' => 'A', 'slug' => 'a', 'urutan' => 1]);
        $kedua = Category::create(['name' => 'B', 'slug' => 'b', 'urutan' => 2]);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->put(route('admin.kategori.geser', $pertama), ['arah' => 'naik'])
            ->assertRedirect();

        $this->assertSame(1, $pertama->fresh()->urutan);
        $this->assertSame(2, $kedua->fresh()->urutan);
    }

    public function test_admin_dapat_menghapus_kategori_kosong(): void
    {
        $kategori = Category::create(['name' => 'Kosong', 'slug' => 'kosong']);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->delete(route('admin.kategori.hapus', $kategori))
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $kategori->id]);
    }

    private function buatKategoriBerisi(int $jumlah): Category
    {
        $kategori = Category::create([
            'name' => 'Terpakai',
            'slug' => 'terpakai',
        ]);

        foreach (range(1, $jumlah) as $n) {
            Document::create([
                'title' => "Dokumen Uji {$n}",
                'classification' => Document::KLASIFIKASI_PUBLIK,
                'ai_allowed' => true,
                'category_id' => $kategori->id,
                'file_path' => 'dokumen/uji.pdf',
                'file_name' => 'uji.pdf',
                'file_size' => 1024,
                'status' => Document::STATUS_READY,
            ]);
        }

        return $kategori;
    }

    /**
     * Tanpa penegasan, tidak boleh ada dokumen yang ikut terhapus -- ini
     * pengaman bila JavaScript mati atau permintaan DELETE dikirim langsung.
     */
    public function test_tanpa_penegasan_kategori_berisi_dokumen_tidak_terhapus(): void
    {
        $kategori = $this->buatKategoriBerisi(2);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->delete(route('admin.kategori.hapus', $kategori))
            ->assertSessionHasErrors('kategori');

        $this->assertDatabaseHas('categories', ['id' => $kategori->id]);
        $this->assertSame(2, Document::where('category_id', $kategori->id)->count());
    }

    /** Dengan penegasan, kategori dan seluruh dokumennya ikut terhapus. */
    public function test_dengan_penegasan_kategori_dan_dokumennya_terhapus(): void
    {
        $kategori = $this->buatKategoriBerisi(3);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->delete(route('admin.kategori.hapus', $kategori), [
                'konfirmasi_hapus_dokumen' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $kategori->id]);
        $this->assertSame(0, Document::count(), 'Dokumen tidak boleh lagi tampil di katalog.');
    }

    /** Dokumen dihapus lunak, jadi masih dapat dipulihkan dari arsip. */
    public function test_dokumen_yang_ikut_terhapus_masih_dapat_dipulihkan(): void
    {
        $kategori = $this->buatKategoriBerisi(2);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->delete(route('admin.kategori.hapus', $kategori), [
                'konfirmasi_hapus_dokumen' => '1',
            ]);

        $this->assertSame(2, Document::onlyTrashed()->count());

        // Akses dokumen tidak disentuh: sejak akses ditentukan per dokumen,
        // hilangnya kategori tidak mengubah siapa yang berhak melihatnya.
        $this->assertSame(
            2,
            Document::onlyTrashed()->where('classification', Document::KLASIFIKASI_PUBLIK)->count(),
        );
    }

    /** F-7.2: tiap dokumen yang ikut terhapus tetap tercatat di log audit. */
    public function test_penghapusan_dokumen_lewat_kategori_tercatat_di_audit(): void
    {
        $kategori = $this->buatKategoriBerisi(3);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->delete(route('admin.kategori.hapus', $kategori), [
                'konfirmasi_hapus_dokumen' => '1',
            ]);

        $this->assertSame(3, \App\Models\AuditLog::where('action', 'dokumen.hapus')->count());
    }

    /** Kategori kosong tetap dapat dihapus tanpa penegasan tambahan. */
    public function test_kategori_kosong_tetap_dapat_dihapus_langsung(): void
    {
        $kategori = Category::create(['name' => 'Kosong2', 'slug' => 'kosong2']);

        $this->actingAs($this->buatPengguna(User::ROLE_ADMIN))
            ->delete(route('admin.kategori.hapus', $kategori))
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $kategori->id]);
    }

    /** F-1.2: pegawai bukan admin, jadi tidak boleh mengelola kategori. */
    public function test_pegawai_ditolak_mengelola_kategori(): void
    {
        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->get(route('admin.kategori.index'))
            ->assertForbidden();

        $this->actingAs($this->buatPengguna(User::ROLE_PEGAWAI))
            ->post(route('admin.kategori.simpan'), [
                'name' => 'Tidak Boleh',
            ])
            ->assertForbidden();
    }

    /**
     * F-8.4: kategori internal tidak pernah muncul sebagai pilihan filter
     * bagi pengunjung publik, walau lewat kotak dropdown sekalipun.
     */
    /**
     * Kategori kini pengelompokan murni, jadi seluruhnya tampil sebagai
     * pilihan filter. Yang tetap disaring hak akses adalah dokumennya.
     */
    public function test_semua_kategori_tampil_sebagai_pilihan_filter(): void
    {
        Category::create(['name' => 'Kategori Apa Pun', 'slug' => 'apa-pun']);

        $this->tamuTerdaftar()->get(route('publik.katalog.index'))
            ->assertOk()
            ->assertSee('Kategori Apa Pun');
    }
}
