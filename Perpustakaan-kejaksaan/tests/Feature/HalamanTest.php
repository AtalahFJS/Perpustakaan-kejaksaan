<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\Document;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memastikan seluruh halaman utama benar-benar dapat dirender.
 * Menangkap galat Blade yang tidak terlihat dari pengujian logika saja.
 */
class HalamanTest extends TestCase
{
    use RefreshDatabase;

    private function dokumenSiap(): Document
    {
        $kategori = Category::create(['name' => 'Pedoman', 'slug' => 'pedoman']);

        $dokumen = Document::create([
            'title' => 'Pedoman Penanganan Perkara',
            'author' => 'Tim Penyusun',
            'year' => 2026,
            'category_id' => $kategori->id,
            'classification' => Document::KLASIFIKASI_INTERNAL,
            'abstract' => 'Abstrak dokumen uji.',
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 2048,
            'page_count' => 12,
            'status' => Document::STATUS_READY,
        ]);

        $dokumen->summaries()->createMany([
            [
                'type' => Summary::TIPE_SINGKAT,
                'content' => 'Ringkasan singkat dokumen uji.',
                'is_approved' => true,
            ],
            [
                'type' => Summary::TIPE_POIN_KUNCI,
                'content' => "- Poin pertama\n- Poin kedua",
                'is_approved' => true,
            ],
            [
                'type' => Summary::TIPE_PER_BAGIAN,
                'scope_label' => 'Halaman 1-6',
                'urutan' => 0,
                'content' => 'Ringkasan bagian pertama.',
                'is_approved' => true,
            ],
        ]);

        return $dokumen;
    }

    public function test_katalog_menampilkan_dokumen_dan_ringkasan(): void
    {
        $dokumen = $this->dokumenSiap();
        $pembaca = $this->buatPengguna(User::ROLE_PEMBACA);

        $this->actingAs($pembaca)
            ->get(route('katalog.index'))
            ->assertOk()
            ->assertSee('Pedoman Penanganan Perkara');

        $this->actingAs($pembaca)
            ->get(route('katalog.show', $dokumen))
            ->assertOk()
            ->assertSee('Ringkasan singkat dokumen uji.')
            ->assertSee('Poin pertama')
            // F-5.4 / NFR 6.5: keluaran AI diberi penanda yang jelas.
            ->assertSee('Dihasilkan AI')
            ->assertSee('perlu diverifikasi', false);
    }

    /** F-3.1 dan F-3.2: pencarian metadata dan isi dokumen. */
    public function test_pencarian_katalog_menyaring_hasil(): void
    {
        $this->dokumenSiap();
        $pembaca = $this->buatPengguna(User::ROLE_PEMBACA);

        $this->actingAs($pembaca)
            ->get(route('katalog.index', ['q' => 'Penanganan']))
            ->assertOk()
            ->assertSee('Pedoman Penanganan Perkara');

        $this->actingAs($pembaca)
            ->get(route('katalog.index', ['q' => 'katakuncitidakada']))
            ->assertOk()
            ->assertSee('Tidak ada dokumen yang cocok.');
    }

    public function test_halaman_pembaca_dapat_dibuka(): void
    {
        $dokumen = $this->dokumenSiap();

        $this->actingAs($this->buatPengguna(User::ROLE_PEMBACA))
            ->get(route('pembaca.show', $dokumen))
            ->assertOk()
            ->assertSee('Baca');
    }

    /** F-6.5: halaman chat menampilkan riwayat percakapan beserta sitasinya. */
    public function test_halaman_chat_menampilkan_riwayat_dan_sitasi(): void
    {
        $dokumen = $this->dokumenSiap();
        $pembaca = $this->buatPengguna(User::ROLE_PEMBACA);

        $sesi = $pembaca->chatSessions()->create(['document_id' => $dokumen->id]);
        $sesi->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => 'Apa isi pedoman ini?',
        ]);
        $sesi->messages()->create([
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Pedoman ini mengatur penanganan perkara [1].',
            'citations' => [['nomor' => 1, 'halaman' => 3, 'kutipan' => 'kutipan uji']],
        ]);

        $this->actingAs($pembaca)
            ->get(route('chat.show', $dokumen))
            ->assertOk()
            ->assertSee('Apa isi pedoman ini?')
            ->assertSee('Hal. 3')
            // F-6.9: peringatan verifikasi selalu ditampilkan.
            ->assertSee('verifikasi', false);
    }

    /** Chat tidak tersedia untuk dokumen yang belum selesai diproses. */
    public function test_chat_ditolak_untuk_dokumen_belum_siap(): void
    {
        $dokumen = $this->dokumenSiap();
        $dokumen->update(['status' => Document::STATUS_PROCESSING]);

        $this->actingAs($this->buatPengguna(User::ROLE_PEMBACA))
            ->get(route('chat.show', $dokumen))
            ->assertForbidden();
    }

    public function test_halaman_pustakawan_dapat_dibuka(): void
    {
        $this->dokumenSiap();
        $pustakawan = $this->buatPengguna(User::ROLE_PUSTAKAWAN);

        $this->actingAs($pustakawan)->get(route('dokumen.index'))->assertOk();
        $this->actingAs($pustakawan)->get(route('dokumen.create'))->assertOk();
        $this->actingAs($pustakawan)
            ->get(route('dokumen.edit', Document::first()))
            ->assertOk();
    }

    public function test_halaman_administrasi_dapat_dibuka(): void
    {
        $this->dokumenSiap();
        $admin = $this->buatPengguna(User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('admin.dasbor'))->assertOk();
        $this->actingAs($admin)->get(route('admin.pengguna'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit'))->assertOk();
        $this->actingAs($admin)->get(route('admin.pemakaian'))->assertOk();
        $this->actingAs($admin)->get(route('admin.riwayat-login'))->assertOk();
    }

    /** F-7.7: ekspor log audit ke CSV. */
    public function test_ekspor_audit_menghasilkan_csv(): void
    {
        $dokumen = $this->dokumenSiap();
        $admin = $this->buatPengguna(User::ROLE_ADMIN);

        // Membuka detail dokumen menghasilkan entri audit (F-7.2).
        $this->actingAs($admin)->get(route('katalog.show', $dokumen));

        $respons = $this->actingAs($admin)->get(route('admin.audit.ekspor'));
        $respons->assertOk();
        $respons->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('dokumen.lihat', $respons->streamedContent());
    }

    /** F-7.2: setiap akses dokumen tercatat di log audit. */
    public function test_akses_dokumen_tercatat_di_log_audit(): void
    {
        $dokumen = $this->dokumenSiap();
        $pembaca = $this->buatPengguna(User::ROLE_PEMBACA);

        $this->actingAs($pembaca)->get(route('katalog.show', $dokumen));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $pembaca->id,
            'action' => 'dokumen.lihat',
            'subject_id' => $dokumen->id,
        ]);
    }

    /** F-5.5: ringkasan yang belum disetujui disembunyikan dari pembaca. */
    public function test_ringkasan_belum_disetujui_disembunyikan_dari_pembaca(): void
    {
        $dokumen = $this->dokumenSiap();
        $dokumen->summaries()->update(['is_approved' => false]);

        $this->actingAs($this->buatPengguna(User::ROLE_PEMBACA))
            ->get(route('katalog.show', $dokumen))
            ->assertOk()
            ->assertDontSee('Ringkasan singkat dokumen uji.');

        // Pustakawan tetap melihatnya untuk ditinjau.
        $this->actingAs($this->buatPengguna(User::ROLE_PUSTAKAWAN))
            ->get(route('katalog.show', $dokumen))
            ->assertOk()
            ->assertSee('Ringkasan singkat dokumen uji.');
    }
}
