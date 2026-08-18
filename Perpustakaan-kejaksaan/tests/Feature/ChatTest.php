<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\Document;
use App\Models\User;
use App\Services\KlienLayananAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Modul chat dengan dokumen (PRD 5.6).
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function dokumenSiap(): Document
    {
        return Document::create([
            'title' => 'Dokumen Uji',
            'classification' => Document::KLASIFIKASI_INTERNAL,
            'file_path' => 'dokumen/uji.pdf',
            'file_name' => 'uji.pdf',
            'file_size' => 1024,
            'status' => Document::STATUS_READY,
        ]);
    }

    /** F-6.1 s.d. F-6.3: jawaban tersimpan lengkap dengan sitasinya. */
    public function test_pertanyaan_menghasilkan_jawaban_dengan_sitasi(): void
    {
        $dokumen = $this->dokumenSiap();
        $pembaca = $this->buatPengguna(User::ROLE_PEMBACA);

        $this->mock(KlienLayananAi::class, function ($tiruan) {
            $tiruan->shouldReceive('tanya')->once()->andReturn([
                'jawaban' => 'Dokumen ini mengatur tata cara penanganan perkara [1].',
                'sitasi' => [['nomor' => 1, 'halaman' => 4, 'kutipan' => 'kutipan uji']],
                'ditemukan' => true,
                'tokens_in' => 1200,
                'tokens_out' => 80,
                'latency_ms' => 950,
            ]);
        });

        $respons = $this->actingAs($pembaca)
            ->postJson(route('chat.tanya', $dokumen), [
                'pertanyaan' => 'Apa isi dokumen ini?',
            ]);

        $respons->assertOk()
            ->assertJsonPath('ditemukan', true)
            ->assertJsonPath('sitasi.0.halaman', 4);

        $this->assertDatabaseHas('chat_messages', [
            'role' => 'user',
            'content' => 'Apa isi dokumen ini?',
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'role' => 'assistant',
            'tokens_in' => 1200,
        ]);
    }

    /**
     * NFR 6.4: kegagalan layanan AI tidak melumpuhkan aplikasi, dan pesannya
     * dapat dipahami pengguna non-teknis.
     */
    public function test_kegagalan_layanan_ai_ditangani_dengan_pesan_yang_jelas(): void
    {
        $dokumen = $this->dokumenSiap();

        $this->mock(KlienLayananAi::class, function ($tiruan) {
            $tiruan->shouldReceive('tanya')
                ->andThrow(new RuntimeException('koneksi ditolak'));
        });

        $respons = $this->actingAs($this->buatPengguna(User::ROLE_PEMBACA))
            ->postJson(route('chat.tanya', $dokumen), ['pertanyaan' => 'Apa isinya?']);

        $respons->assertStatus(503);
        $this->assertStringContainsString('tidak dapat dihubungi', $respons->json('galat'));

        // Pertanyaan yang gagal tidak menyisakan pesan menggantung.
        $this->assertDatabaseCount('chat_messages', 0);
    }

    /** F-7.6: kuota pemakaian AI per pengguna per hari. */
    public function test_kuota_harian_menghentikan_pertanyaan_berikutnya(): void
    {
        config()->set('pustakaai.kuota.chat_per_hari', 2);

        $dokumen = $this->dokumenSiap();
        $pembaca = $this->buatPengguna(User::ROLE_PEMBACA);

        // Dua pemanggilan sudah terpakai hari ini.
        for ($i = 0; $i < 2; $i++) {
            AiUsageLog::create([
                'user_id' => $pembaca->id,
                'document_id' => $dokumen->id,
                'operation' => 'chat',
                'model' => 'uji',
                'created_at' => now(),
            ]);
        }

        $this->mock(KlienLayananAi::class, function ($tiruan) {
            $tiruan->shouldNotReceive('tanya');
        });

        $this->actingAs($pembaca)
            ->postJson(route('chat.tanya', $dokumen), ['pertanyaan' => 'Pertanyaan ketiga?'])
            ->assertStatus(429)
            ->assertJsonPath('galat', 'Kuota tanya jawab harian Anda sudah habis. Silakan coba lagi besok.');
    }

    /** F-8.2: dokumen terbatas tidak dapat ditanyakan sama sekali. */
    public function test_dokumen_terbatas_tidak_dapat_ditanyakan(): void
    {
        $dokumen = $this->dokumenSiap();
        $dokumen->update(['classification' => Document::KLASIFIKASI_TERBATAS]);

        $this->mock(KlienLayananAi::class, function ($tiruan) {
            $tiruan->shouldNotReceive('tanya');
        });

        // Bahkan bagi pustakawan yang boleh melihatnya, fitur tanya ditutup.
        $this->actingAs($this->buatPengguna(User::ROLE_PUSTAKAWAN))
            ->postJson(route('chat.tanya', $dokumen), ['pertanyaan' => 'Apa isinya?'])
            ->assertForbidden();
    }

    /** F-6.7: umpan balik tersimpan pada pesan yang bersangkutan. */
    public function test_umpan_balik_tersimpan(): void
    {
        $dokumen = $this->dokumenSiap();
        $pembaca = $this->buatPengguna(User::ROLE_PEMBACA);

        $sesi = $pembaca->chatSessions()->create(['document_id' => $dokumen->id]);
        $pesan = $sesi->messages()->create(['role' => 'assistant', 'content' => 'jawaban']);

        $this->actingAs($pembaca)
            ->postJson(route('chat.umpan-balik', $pesan), ['nilai' => 1])
            ->assertOk();

        $this->assertSame(1, $pesan->fresh()->feedback);
    }

    /** Umpan balik hanya boleh diberikan pemilik percakapan. */
    public function test_umpan_balik_pengguna_lain_ditolak(): void
    {
        $dokumen = $this->dokumenSiap();
        $pemilik = $this->buatPengguna(User::ROLE_PEMBACA);
        $orangLain = $this->buatPengguna(User::ROLE_PEMBACA);

        $sesi = $pemilik->chatSessions()->create(['document_id' => $dokumen->id]);
        $pesan = $sesi->messages()->create(['role' => 'assistant', 'content' => 'jawaban']);

        $this->actingAs($orangLain)
            ->postJson(route('chat.umpan-balik', $pesan), ['nilai' => -1])
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
