<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Document;
use App\Models\Summary;
use App\Services\PencatatAudit;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modul pencarian dan katalog (PRD 5.3).
 */
class KatalogController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * F-3.1 pencarian metadata, F-3.2 pencarian teks penuh,
     * F-3.4 filter, F-3.5 pengurutan.
     */
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
        return view('katalog.index', [
            'dokumenTerbaru' => Document::query()
                ->with('category')
                ->withJumlahDilihat()
                ->terlihatOleh($request->user())
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
                ->terlihatOleh($request->user())
                // whereHas, bukan having(): alias subkueri tidak dapat
                // dipakai di HAVING pada PostgreSQL (hanya di ORDER BY).
                ->whereHas('viewLogs')
                ->orderByDesc('dilihat_count')
                ->take(6)
                ->get(),

            // Kategori tampil untuk semua; yang disaring hak akses adalah
            // hitungan dokumennya.
            'kategoriUtama' => Category::query()
                ->withCount(['documents' => fn (Builder $q) => $q->terlihatOleh($request->user())])
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
            ->terlihatOleh($request->user())
            ->when($request->filled('kategori'), fn (Builder $q) => $q->where('category_id', $request->integer('kategori')))
            ->when($request->filled('tahun'), fn (Builder $q) => $q->where('year', $request->integer('tahun')))
            ->when($request->filled('klasifikasi'), fn (Builder $q) => $q->where('classification', $request->query('klasifikasi')))
            ->when($kueri !== '', fn (Builder $q) => $q->pencarian($kueri));

        return view('katalog.semua', [
            'dokumen' => $this->terapkanUrutan($kueriDokumen, $urutan, $kueri)
                ->paginate(config('pustakaai.katalog.per_halaman'))
                ->withQueryString(),
            'kueri' => $kueri,
            'urutan' => $urutan,
            'sedangMenyaring' => $request->hasAny(['q', 'kategori', 'tahun', 'klasifikasi']),
            'kategoriSemua' => Category::query()->terurut()->get(),
            'tahunSemua' => Document::query()
                ->terlihatOleh($request->user())
                ->whereNotNull('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->all(),
        ]);
    }

    public function show(Request $request, Document $document): View
    {
        $this->authorize('view', $document);

        $document->load(['category', 'tags', 'uploader']);

        // F-5.5: hanya ringkasan yang disetujui yang tampil bagi non-admin.
        $ringkasan = $document->summaries()
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('is_approved', true))
            ->orderBy('urutan')
            ->get()
            ->groupBy('type');

        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_LIHAT, $document);

        return view('katalog.show', [
            'dokumen' => $document,
            'ringkasanSingkat' => $ringkasan->get(Summary::TIPE_SINGKAT)?->first(),
            'ringkasanPoin' => $ringkasan->get(Summary::TIPE_POIN_KUNCI)?->first(),
            'ringkasanBagian' => $ringkasan->get(Summary::TIPE_PER_BAGIAN) ?? collect(),
            'sesiChat' => $request->user()->chatSessions()
                ->where('document_id', $document->id)
                ->latest()
                ->first(),
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

    private function tahunTersedia(Request $request): array
    {
        return Document::query()
            ->terlihatOleh($request->user())
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->all();
    }
}
