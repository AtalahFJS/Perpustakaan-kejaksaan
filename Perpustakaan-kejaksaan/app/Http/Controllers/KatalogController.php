<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Document;
use App\Models\Summary;
use App\Services\PencatatAudit;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function index(Request $request): View
    {
        $kueri = trim((string) $request->query('q', ''));
        $urutan = $request->query('urut', $kueri !== '' ? 'relevansi' : 'terbaru');

        $kueriDokumen = Document::query()
            ->with(['category', 'tags'])
            ->terlihatOleh($request->user())
            ->when($request->filled('kategori'), fn (Builder $q) => $q->where('category_id', $request->integer('kategori')))
            ->when($request->filled('tahun'), fn (Builder $q) => $q->where('year', $request->integer('tahun')))
            ->when($request->filled('penulis'), fn (Builder $q) => $q->where('author', 'ilike', '%'.$request->query('penulis').'%'))
            ->when($request->filled('klasifikasi'), fn (Builder $q) => $q->where('classification', $request->query('klasifikasi')))
            ->when($kueri !== '', fn (Builder $q) => $this->terapkanPencarian($q, $kueri));

        $dokumen = $this->terapkanUrutan($kueriDokumen, $urutan, $kueri)
            ->paginate(config('pustakaai.katalog.per_halaman'))
            ->withQueryString();

        return view('katalog.index', [
            'dokumen' => $dokumen,
            'kueri' => $kueri,
            'urutan' => $urutan,
            'kategoriSemua' => Category::orderBy('name')->get(),
            'tahunSemua' => $this->tahunTersedia($request),
        ]);
    }

    /**
     * F-3.7: halaman detail -- metadata, sampul, ringkasan,
     * tombol baca dan tanya.
     */
    public function show(Request $request, Document $document): View
    {
        $this->authorize('view', $document);

        $document->load(['category', 'tags', 'uploader']);

        // F-5.5: hanya ringkasan yang disetujui yang tampil bagi pembaca.
        $ringkasan = $document->summaries()
            ->when(! $request->user()->isPustakawan(), fn ($q) => $q->where('is_approved', true))
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

    /**
     * Pencarian gabungan: teks penuh pada metadata (F-3.1) dan pada isi
     * dokumen lewat tabel chunks (F-3.2). Keduanya memakai indeks GIN.
     */
    private function terapkanPencarian(Builder $kueriDokumen, string $kata): Builder
    {
        return $kueriDokumen->where(function (Builder $q) use ($kata) {
            $q->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$kata])
                ->orWhere('title', 'ilike', '%'.$kata.'%')
                ->orWhere('author', 'ilike', '%'.$kata.'%')
                ->orWhereExists(function ($sub) use ($kata) {
                    $sub->select(DB::raw(1))
                        ->from('chunks')
                        ->whereColumn('chunks.document_id', 'documents.id')
                        ->whereRaw("chunks.search_vector @@ plainto_tsquery('simple', ?)", [$kata]);
                });
        });
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
