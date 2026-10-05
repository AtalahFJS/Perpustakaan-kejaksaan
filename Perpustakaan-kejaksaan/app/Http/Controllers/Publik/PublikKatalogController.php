<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use App\Models\Summary;
use App\Services\PencatatAudit;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Katalog untuk pengunjung tanpa login (pembaruan 21 Agustus 2026,
 * hasil wawancara "user umum benar-benar publik tanpa login").
 *
 * Hanya dokumen berklasifikasi publik DALAM kategori publik yang tampil
 * -- lihat Document::scopeTerlihatOleh(null) dan Category::bolehDilihatOleh(null).
 * Fitur tanya-jawab AI (chat) sengaja tidak disediakan di sini supaya
 * kuota/biaya layanan AI tidak dapat disalahgunakan pengunjung anonim.
 */
class PublikKatalogController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * Beranda katalog: etalase saja -- sorotan dokumen terbaru, yang banyak
     * dicari, dan telusur per kategori.
     *
     * Daftar lengkap dokumen sengaja TIDAK ada di sini (pembaruan 26 Agustus
     * 2026). Menumpuk seluruh koleksi di beranda membuatnya panjang dan sulit
     * dipindai; daftar penuh beserta pencarian dan penyaringnya kini berdiri
     * di halaman tersendiri -- lihat semua().
     */
    public function index(Request $request): View
    {
        return view('publik.katalog.index', [
            'dokumenTerbaru' => Document::query()
                ->with('category')
                ->withJumlahDilihat()
                ->terlihatOleh(null)
                // Urut tanggal rilis dokumen, bukan waktu unggahnya.
                ->orderByRaw('tanggal_terbit DESC NULLS LAST')
                ->latest()
                ->take(10)
                ->get(),

            // "Banyak dicari" memakai penghitung yang sudah ada: berapa ORANG
            // berbeda pernah membuka dokumen (lihat scopeWithJumlahDilihat).
            'dokumenBanyakDilihat' => Document::query()
                ->with('category')
                ->withJumlahDilihat()
                ->terlihatOleh(null)
                // whereHas, bukan having(): alias subkueri tidak dapat
                // dipakai di HAVING pada PostgreSQL (hanya di ORDER BY).
                ->whereHas('viewLogs')
                ->orderByDesc('dilihat_count')
                ->take(6)
                ->get(),

            // Kategori tampil untuk semua; yang disaring hak akses adalah
            // hitungan dokumennya.
            'kategoriUtama' => Category::query()
                ->withCount(['documents' => fn (Builder $q) => $q->terlihatOleh(null)])
                ->terurut()
                ->get(),
        ]);
    }

    /**
     * Daftar lengkap dokumen beserta pencarian, penyaring, dan pengurutan
     * (F-3.1 s.d. F-3.5). Halaman inilah tujuan tombol "Lihat semua" serta
     * kotak pencarian di beranda.
     */
    public function semua(Request $request): View
    {
        $kueri = trim((string) $request->query('q', ''));
        $urutan = $request->query('urut', $kueri !== '' ? 'relevansi' : 'terbaru');

        $kueriDokumen = Document::query()
            ->with(['category', 'tags'])
            ->withJumlahDilihat()
            ->terlihatOleh(null)
            ->when($request->filled('kategori'), fn (Builder $q) => $q->where('category_id', $request->integer('kategori')))
            ->when($request->filled('tahun'), fn (Builder $q) => $q->where('year', $request->integer('tahun')))
            ->when($kueri !== '', fn (Builder $q) => $q->pencarian($kueri));

        return view('publik.katalog.semua', [
            'dokumen' => $this->terapkanUrutan($kueriDokumen, $urutan, $kueri)
                ->paginate(config('pustakaai.katalog.per_halaman'))
                ->withQueryString(),
            'kueri' => $kueri,
            'urutan' => $urutan,
            'sedangMenyaring' => $request->hasAny(['q', 'kategori', 'tahun', 'klasifikasi']),
            'kategoriSemua' => Category::query()->terurut()->get(),
            'tahunSemua' => Document::query()
                ->terlihatOleh(null)
                ->whereNotNull('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->all(),
        ]);
    }

    public function show(Document $document): View
    {
        abort_unless($document->bolehDilihatOleh(null), 404);

        $document->load(['category', 'tags']);

        // F-5.5: pengunjung publik hanya melihat ringkasan yang sudah disetujui.
        $ringkasan = $document->summaries()
            ->where('is_approved', true)
            ->orderBy('urutan')
            ->get()
            ->groupBy('type');

        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_LIHAT, $document, ['akses' => 'publik']);

        return view('publik.katalog.show', [
            'dokumen' => $document,
            'ringkasanSingkat' => $ringkasan->get(Summary::TIPE_SINGKAT)?->first(),
            'ringkasanPoin' => $ringkasan->get(Summary::TIPE_POIN_KUNCI)?->first(),
            'ringkasanBagian' => $ringkasan->get(Summary::TIPE_PER_BAGIAN) ?? collect(),
        ]);
    }

    private function terapkanUrutan(Builder $kueri, string $urutan, string $kata): Builder
    {
        return match ($urutan) {
            'judul' => $kueri->orderBy('title'),
            'tahun' => $kueri->orderByRaw('year DESC NULLS LAST'),
            'relevansi' => $kata !== ''
                ? $kueri->orderByRaw(
                    "ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC, created_at DESC",
                    [$kata]
                )
                : $kueri->latest(),
            default => $kueri->latest(),
        };
    }
}
