<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Document;
use App\Services\PencatatAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manajemen kategori untuk admin.
 *
 * Pembaruan 26 Agustus 2026: kategori kembali menjadi pengelompokan murni.
 * Ia tidak lagi menentukan siapa yang boleh melihat dokumen -- itu urusan
 * klasifikasi per dokumen. Sebagai gantinya admin dapat menyusun sendiri
 * urutan tampil kategori di katalog.
 */
class KategoriController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function index(): View
    {
        return view('admin.kategori', [
            'kategoriSemua' => Category::withCount('documents')->terurut()->get(),
        ]);
    }

    /**
     * Dipakai dua jalur: formulir penuh di halaman Kelola Kategori, dan
     * "tambah kategori" ringkas di dalam formulir unggah dokumen. Yang
     * kedua memanggil lewat fetch() dan menerima JSON -- formulir unggah
     * memuat berkas PDF yang sudah dipilih, jadi memuat ulang halaman
     * hanya untuk menambah kategori akan membuang berkas dan seluruh
     * metadata yang sudah diketik.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Nama unik: pada tambah-cepat, admin tidak melihat daftar
            // kategori yang sudah ada, sehingga kembar sangat mudah terjadi
            // dan menyisakan dua pilihan serupa di menu tarik-turun.
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
        ], [
            'name.unique' => 'Kategori dengan nama ini sudah ada.',
        ]);

        // bootstrap/app.php membatasi render JSON otomatis ke jalur api/*,
        // sehingga ValidationException di sini akan berubah jadi redirect.
        // Pemanggil fetch() butuh 422 berisi pesan agar dapat menampilkannya
        // di panel tanpa memuat ulang halaman -- jadi ditangani eksplisit.
        if ($validator->fails() && $request->wantsJson()) {
            return response()->json([
                'message' => 'Kategori gagal disimpan.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validate();

        $kategori = Category::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::random(4),
            // Kategori baru ditempatkan di urutan paling bawah.
            'urutan' => (int) Category::max('urutan') + 1,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $kategori->id,
                'name' => $kategori->name,
                // Label disusun di server supaya penulisannya persis sama
                // dengan pilihan yang dirender Blade pada muat halaman biasa.
                // Sejak kategori tidak lagi menentukan akses, tidak ada lagi
                // keterangan "(publik)/(internal)" yang perlu diikutkan.
                'label' => $kategori->name,
            ], 201);
        }

        return back()->with('sukses', 'Kategori baru ditambahkan.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
        ], [
            'name.unique' => 'Kategori dengan nama ini sudah ada.',
        ]);

        $category->update($data);

        // Tidak ada lagi penyelarasan akses dokumen di sini: sejak
        // 26 Agustus 2026 kategori tidak menentukan siapa yang boleh melihat,
        // jadi mengganti nama kategori tidak boleh menyentuh akses dokumennya.
        return back()->with('sukses', 'Kategori diperbarui.');
    }

    /**
     * Menggeser satu kategori satu langkah ke atas atau ke bawah.
     *
     * Sengaja tukar-posisi dengan tetangganya, bukan menulis ulang seluruh
     * nomor urut: hanya dua baris yang tersentuh, sehingga dua admin yang
     * menyusun bersamaan tidak saling menimpa urutan satu sama lain.
     */
    public function geser(Request $request, Category $category): RedirectResponse
    {
        $arah = $request->string('arah')->toString();
        abort_unless(in_array($arah, ['naik', 'turun'], true), 422);

        $tetangga = Category::query()
            ->when($arah === 'naik',
                fn ($q) => $q->where('urutan', '<', $category->urutan)->orderByDesc('urutan'),
                fn ($q) => $q->where('urutan', '>', $category->urutan)->orderBy('urutan'),
            )
            ->first();

        // Sudah di ujung -- tidak ada yang perlu ditukar.
        if (! $tetangga) {
            return back();
        }

        DB::transaction(function () use ($category, $tetangga) {
            [$a, $b] = [$category->urutan, $tetangga->urutan];
            $category->update(['urutan' => $b]);
            $tetangga->update(['urutan' => $a]);
        });

        return back()->with('sukses', 'Urutan kategori diperbarui.');
    }

    /**
     * Menghapus kategori beserta dokumen di dalamnya.
     *
     * Sebelumnya penghapusan ditolak selama kategori masih memuat dokumen.
     * Kini dokumennya ikut terhapus, tetapi dengan tiga pengaman:
     *
     *   1. Formulir wajib menyertakan penegasan (dua langkah di antarmuka),
     *      sehingga permintaan DELETE yang tidak disengaja tidak menghapus
     *      dokumen apa pun.
     *   2. Dokumen dihapus lunak -- masih dapat dipulihkan dari "Arsip
     *      terhapus" pada Kelola Dokumen.
     *   3. Setiap dokumen tetap tercatat di log audit (F-7.2).
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $dokumen = $category->documents()->get();
        $jumlah = $dokumen->count();

        if ($jumlah > 0 && ! $request->boolean('konfirmasi_hapus_dokumen')) {
            return back()->withErrors([
                'kategori' => "Penghapusan dibatalkan: kategori ini memuat {$jumlah} dokumen dan "
                    .'penghapusannya belum ditegaskan.',
            ]);
        }

        foreach ($dokumen as $satu) {
            $this->audit->catatDokumen(PencatatAudit::DOKUMEN_HAPUS, $satu, [
                'sebab' => 'kategori dihapus',
                'kategori' => $category->name,
            ]);
        }

        if ($jumlah > 0) {
            // Tingkat akses dokumen dibiarkan apa adanya: sejak akses kembali
            // ditentukan per dokumen, hilangnya kategori tidak mengubah siapa
            // yang berhak melihatnya. Kolom category_id sendiri menjadi NULL
            // karena kunci asingnya ber-SET NULL.
            $category->documents()->delete();
        }

        $category->delete();

        return back()->with('sukses', $jumlah > 0
            ? "Kategori dihapus beserta {$jumlah} dokumen di dalamnya. "
              .'Dokumen masih dapat dipulihkan dari Arsip terhapus pada Kelola Dokumen.'
            : 'Kategori dihapus.');
    }
}
