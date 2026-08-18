<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\KatalogController;
use App\Http\Controllers\PembacaController;
use App\Http\Controllers\RingkasanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute PustakaAI
|--------------------------------------------------------------------------
| Seluruh isi perpustakaan berada di balik autentikasi (F-4.3): tidak ada
| berkas maupun metadata dokumen yang dapat diakses tanpa masuk.
*/

Route::redirect('/', '/katalog');

// --- Tamu ---
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'formMasuk'])->name('masuk');
    Route::post('/masuk', [AuthController::class, 'masuk'])->middleware('throttle:10,1');
});

Route::post('/keluar', [AuthController::class, 'keluar'])
    ->middleware('auth')
    ->name('keluar');

// --- Terautentikasi ---
Route::middleware('auth')->group(function () {

    // Katalog dan pencarian (PRD 5.3)
    Route::get('/katalog', [KatalogController::class, 'index'])->name('katalog.index');
    Route::get('/katalog/{document}', [KatalogController::class, 'show'])->name('katalog.show');

    // Pembaca dokumen (PRD 5.4)
    Route::get('/baca/{document}', [PembacaController::class, 'show'])->name('pembaca.show');
    Route::post('/baca/{document}/penanda', [PembacaController::class, 'simpanPenanda'])->name('pembaca.penanda');

    // Penyajian berkas -- satu-satunya jalur akses ke PDF (F-4.3, NFR 6.2)
    Route::get('/berkas/{document}', [BerkasController::class, 'berkas'])->name('berkas.lihat');
    Route::get('/berkas/{document}/unduh', [BerkasController::class, 'unduh'])->name('berkas.unduh');
    Route::get('/berkas/{document}/sampul', [BerkasController::class, 'sampul'])->name('berkas.sampul');

    // Chat RAG (PRD 5.6). NFR 6.2: endpoint AI dibatasi lajunya.
    Route::get('/tanya/{document}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/tanya/{document}', [ChatController::class, 'tanya'])
        ->middleware('throttle:20,1')
        ->name('chat.tanya');
    Route::delete('/tanya/{document}', [ChatController::class, 'bersihkan'])->name('chat.bersihkan');
    Route::post('/pesan/{message}/umpan-balik', [ChatController::class, 'umpanBalik'])->name('chat.umpan-balik');

    // --- Pustakawan dan admin (PRD 5.2, 5.5) ---
    Route::middleware('peran:pustakawan')->group(function () {
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

    // --- Admin saja (PRD 5.7) ---
    Route::middleware('peran:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dasbor'])->name('dasbor');
        Route::get('/pengguna', [AdminController::class, 'pengguna'])->name('pengguna');
        Route::post('/pengguna', [AdminController::class, 'simpanPengguna'])->name('pengguna.simpan');
        Route::put('/pengguna/{user}', [AdminController::class, 'ubahPengguna'])->name('pengguna.ubah');
        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
        Route::get('/audit/ekspor', [AdminController::class, 'eksporAudit'])->name('audit.ekspor');
        Route::get('/pemakaian-ai', [AdminController::class, 'pemakaianAi'])->name('pemakaian');
        Route::get('/riwayat-login', [AdminController::class, 'riwayatLogin'])->name('riwayat-login');
        Route::delete('/dokumen/{id}/hapus-permanen', [DokumenController::class, 'forceDestroy'])->name('dokumen.force-destroy');
    });
});
