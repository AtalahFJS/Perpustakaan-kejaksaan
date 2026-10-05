<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AsistenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\BukuTamuController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\KatalogController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\PembacaController;
use App\Http\Controllers\Publik\PublikBerkasController;
use App\Http\Controllers\Publik\PublikKatalogController;
use App\Http\Controllers\Publik\PublikPembacaController;
use App\Http\Controllers\RingkasanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Perpustakaan Kejaksaan Tinggi Jawa Barat
|--------------------------------------------------------------------------
| Pembaruan 21 Agustus 2026: katalog kini punya dua wajah.
|   - Jalur publik (/publik/...) -- tanpa login, hanya dokumen berklasifikasi
|     publik dalam kategori publik yang tampil. Lihat App\Http\Controllers\Publik.
|   - Jalur internal (di bawah middleware 'auth') -- perlu masuk sebagai
|     Pegawai atau Admin, membuka konten internal serta fitur AI.
| Berkas PDF tetap tidak pernah dapat diakses lewat URL langsung di kedua
| jalur; semua permintaan melewati controller yang memeriksa otorisasi.
*/

// Gerbang: pengunjung memilih Tamu atau Pegawai sebelum masuk katalog.
Route::get('/', [BukuTamuController::class, 'gerbang'])->name('gerbang');
Route::get('/buku-tamu', [BukuTamuController::class, 'form'])->name('buku-tamu.form');
Route::post('/buku-tamu', [BukuTamuController::class, 'simpan'])
    ->middleware('throttle:10,1')
    ->name('buku-tamu.simpan');

// --- Publik: tanpa login sama sekali ---
// NFR 6.2: dibatasi lajunya karena tanpa akun, tidak ada identitas
// pengguna yang bisa dijadikan penanda penyalahgunaan selain IP.
Route::prefix('publik')->name('publik.')->middleware(['throttle:60,1', 'buku-tamu'])->group(function () {
    Route::get('/', [PublikKatalogController::class, 'index'])->name('katalog.index');
    // Wajib didaftarkan sebelum '/{document}', kalau tidak kata "semua" akan
    // ditafsirkan sebagai id dokumen.
    Route::get('/semua', [PublikKatalogController::class, 'semua'])->name('katalog.semua');
    Route::get('/{document}', [PublikKatalogController::class, 'show'])->name('katalog.show');
    Route::get('/{document}/baca', [PublikPembacaController::class, 'show'])->name('pembaca.show');
    Route::get('/{document}/berkas', [PublikBerkasController::class, 'berkas'])->name('berkas.lihat');
    Route::get('/{document}/sampul', [PublikBerkasController::class, 'sampul'])->name('berkas.sampul');
});

// --- Tamu (belum masuk) ---
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'formMasuk'])->name('masuk');
    Route::post('/masuk', [AuthController::class, 'masuk'])->middleware('throttle:10,1');
});

Route::post('/keluar', [AuthController::class, 'keluar'])
    ->middleware('auth')
    ->name('keluar');

// --- Terautentikasi (Pegawai & Admin) ---
Route::middleware('auth')->group(function () {

    // Katalog dan pencarian (PRD 5.3) -- mencakup konten internal
    Route::get('/katalog', [KatalogController::class, 'index'])->name('katalog.index');
    // Sama seperti jalur publik: literal lebih dulu daripada {document}.
    Route::get('/katalog/semua', [KatalogController::class, 'semua'])->name('katalog.semua');
    Route::get('/katalog/{document}', [KatalogController::class, 'show'])->name('katalog.show');

    // Pembaca dokumen (PRD 5.4)
    Route::get('/baca/{document}', [PembacaController::class, 'show'])->name('pembaca.show');
    Route::post('/baca/{document}/penanda', [PembacaController::class, 'simpanPenanda'])->name('pembaca.penanda');

    // Penyajian berkas -- satu-satunya jalur akses ke PDF (F-4.3, NFR 6.2)
    Route::get('/berkas/{document}', [BerkasController::class, 'berkas'])->name('berkas.lihat');
    Route::get('/berkas/{document}/unduh', [BerkasController::class, 'unduh'])->name('berkas.unduh');
    Route::get('/berkas/{document}/sampul', [BerkasController::class, 'sampul'])->name('berkas.sampul');

    // Asisten tanya-jawab lintas dokumen -- dipanggil dari widget mengambang
    // di layouts.app. Sengaja di balik login sama seperti chat per-dokumen,
    // agar kuota AI tidak disalahgunakan tamu.
    Route::post('/asisten', [AsistenController::class, 'tanya'])
        ->middleware('throttle:20,1')
        ->name('asisten.tanya');

    // Chat RAG (PRD 5.6). NFR 6.2: endpoint AI dibatasi lajunya.
    // Sengaja tetap di balik login -- mencegah kuota/biaya API disalahgunakan
    // pengunjung anonim.
    Route::get('/tanya/{document}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/tanya/{document}', [ChatController::class, 'tanya'])
        ->middleware('throttle:20,1')
        ->name('chat.tanya');
    Route::delete('/tanya/{document}', [ChatController::class, 'bersihkan'])->name('chat.bersihkan');
    Route::post('/pesan/{message}/umpan-balik', [ChatController::class, 'umpanBalik'])->name('chat.umpan-balik');

    // --- Admin saja (PRD 5.2, 5.5, 5.7) ---
    // Sejak pembaruan 21 Agustus 2026, kewenangan mengelola konten
    // (dulu milik Pustakawan) sepenuhnya berpindah ke Admin.
    Route::middleware('peran:admin')->group(function () {
        Route::get('/dokumen', [DokumenController::class, 'index'])->name('dokumen.index');
        Route::get('/dokumen/baru', [DokumenController::class, 'create'])->name('dokumen.create');
        Route::post('/dokumen', [DokumenController::class, 'store'])->name('dokumen.store');
        Route::get('/dokumen/{document}/ubah', [DokumenController::class, 'edit'])->name('dokumen.edit');
        Route::put('/dokumen/{document}', [DokumenController::class, 'update'])->name('dokumen.update');
        Route::delete('/dokumen/{document}', [DokumenController::class, 'destroy'])->name('dokumen.destroy');
        Route::post('/dokumen/{id}/pulihkan', [DokumenController::class, 'restore'])->name('dokumen.restore');
        Route::post('/dokumen/{document}/ingest-ulang', [DokumenController::class, 'ingestUlang'])->name('dokumen.ingest-ulang');

        Route::put('/ringkasan/{summary}', [RingkasanController::class, 'update'])->name('ringkasan.update');
        Route::post('/ringkasan/{summary}/setujui', [RingkasanController::class, 'setujui'])->name('ringkasan.setujui');
        Route::post('/dokumen/{document}/ringkasan/buat-ulang', [RingkasanController::class, 'buatUlang'])->name('ringkasan.buat-ulang');
    });

    Route::middleware('peran:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dasbor'])->name('dasbor');
        Route::get('/pengguna', [AdminController::class, 'pengguna'])->name('pengguna');
        Route::post('/pengguna', [AdminController::class, 'simpanPengguna'])->name('pengguna.simpan');
        Route::put('/pengguna/{user}', [AdminController::class, 'ubahPengguna'])->name('pengguna.ubah');
        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
        Route::get('/audit/ekspor', [AdminController::class, 'eksporAudit'])->name('audit.ekspor');
        Route::get('/pemakaian-ai', [AdminController::class, 'pemakaianAi'])->name('pemakaian');
        Route::get('/riwayat-login', [AdminController::class, 'riwayatLogin'])->name('riwayat-login');
        Route::get('/buku-tamu', [BukuTamuController::class, 'daftar'])->name('buku-tamu');
        Route::delete('/dokumen/{id}/hapus-permanen', [DokumenController::class, 'forceDestroy'])->name('dokumen.force-destroy');

        // Manajemen kategori -- baru (pembaruan 21 Agustus 2026): admin
        // menambah/menghapus kategori dan menentukan visibilitasnya.
        Route::get('/kategori', [KategoriController::class, 'index'])->name('kategori.index');
        Route::post('/kategori', [KategoriController::class, 'store'])->name('kategori.simpan');
        Route::put('/kategori/{category}', [KategoriController::class, 'update'])->name('kategori.ubah');
        Route::put('/kategori/{category}/geser', [KategoriController::class, 'geser'])->name('kategori.geser');
        Route::delete('/kategori/{category}', [KategoriController::class, 'destroy'])->name('kategori.hapus');
    });
});
