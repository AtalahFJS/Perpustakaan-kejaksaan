<?php

namespace App\Http\Controllers;

use App\Jobs\ProsesIngestDokumen;
use App\Models\Category;
use App\Models\Document;
use App\Models\Tag;
use App\Services\PencatatAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Modul manajemen dokumen (PRD 5.2). Seluruh aksi di sini
 * membutuhkan peran admin.
 */
class DokumenController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /** Daftar kelola untuk admin, termasuk status pemrosesan (F-2.9). */
    public function index(Request $request): View
    {
        $dokumen = Document::query()
            ->with(['category', 'uploader'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'ilike', '%'.$request->query('q').'%'))
            ->when($request->boolean('terhapus'), fn ($q) => $q->onlyTrashed())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dokumen.index', ['dokumen' => $dokumen]);
    }

    public function create(): View
    {
        return view('dokumen.form', [
            'dokumen' => new Document(['classification' => Document::KLASIFIKASI_INTERNAL, 'language' => 'id']),
            'kategoriSemua' => Category::orderBy('name')->get(),
        ]);
    }

    /**
     * F-2.1 unggah PDF maks 100 MB, F-2.3 metadata wajib,
     * F-8.3 konfirmasi klasifikasi sebelum ingest dimulai.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request, wajibBerkas: true);

        $berkas = $request->file('berkas');

        // NFR 6.2: validasi tipe berdasarkan isi berkas, bukan ekstensi.
        $this->pastikanPdfAsli($berkas->getRealPath());

        $dokumen = new Document($data['metadata']);
        $dokumen->uploaded_by = $request->user()->id;
        $dokumen->file_name = $berkas->getClientOriginalName();
        $dokumen->file_size = $berkas->getSize();
        $dokumen->file_mime = 'application/pdf';
        $dokumen->file_hash = hash_file('sha256', $berkas->getRealPath());

        // Disk privat -- di luar document root (NFR 6.2).
        $dokumen->file_path = $berkas->storeAs(
            'dokumen/'.now()->format('Y/m'),
            Str::uuid()->toString().'.pdf'
        );

        $dokumen->status = $dokumen->bolehDiprosesAi()
            ? Document::STATUS_UPLOADED
            : Document::STATUS_KATALOG_SAJA;

        $dokumen->save();
        $dokumen->tags()->sync($this->tagIds($data['tag']));

        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_UNGGAH, $dokumen);

        // F-8.2: dokumen yang tidak diizinkan tidak diproses AI sama sekali.
        if ($dokumen->bolehDiprosesAi()) {
            ProsesIngestDokumen::dispatch($dokumen->id);
            $pesan = 'Dokumen tersimpan dan sedang diproses di latar belakang.';
        } else {
            $pesan = 'Dokumen tersimpan. Karena tidak diizinkan diproses AI, dokumen hanya masuk katalog.';
        }

        return redirect()->route('dokumen.index')->with('sukses', $pesan);
    }

    public function edit(Document $document): View
    {
        return view('dokumen.form', [
            'dokumen' => $document->load('tags'),
            'kategoriSemua' => Category::orderBy('name')->get(),
        ]);
    }

    /** F-2.6: admin dapat mengedit metadata dan mengganti berkas. */
    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $this->validasi($request, wajibBerkas: false);

        $bolehAiSebelumnya = $document->bolehDiprosesAi();
        $document->fill($data['metadata']);

        $berkasBaru = $request->file('berkas');
        if ($berkasBaru) {
            $this->pastikanPdfAsli($berkasBaru->getRealPath());

            Storage::delete($document->file_path);

            $document->file_path = $berkasBaru->storeAs(
                'dokumen/'.now()->format('Y/m'),
                Str::uuid()->toString().'.pdf'
            );
            $document->file_name = $berkasBaru->getClientOriginalName();
            $document->file_size = $berkasBaru->getSize();
            $document->file_hash = hash_file('sha256', $berkasBaru->getRealPath());
            $document->page_count = null;
        }

        // Berkas berganti, atau dokumen yang tadinya dilarang kini diizinkan
        // diproses AI -- keduanya menuntut pemrosesan ulang.
        $perluIngestUlang = $berkasBaru
            || (! $bolehAiSebelumnya && $document->bolehDiprosesAi());

        if (! $document->bolehDiprosesAi()) {
            // Izin AI dicabut -- buang seluruh turunan AI.
            $document->chunks()->delete();
            $document->summaries()->delete();
            $document->status = Document::STATUS_KATALOG_SAJA;
        } elseif ($perluIngestUlang) {
            $document->status = Document::STATUS_UPLOADED;
            $document->error_message = null;
        }

        $document->save();
        $document->tags()->sync($this->tagIds($data['tag']));

        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_UBAH, $document, [
            'berkas_diganti' => (bool) $berkasBaru,
        ]);

        if ($perluIngestUlang && $document->bolehDiprosesAi()) {
            ProsesIngestDokumen::dispatch($document->id);
        }

        return redirect()->route('dokumen.index')->with('sukses', 'Perubahan tersimpan.');
    }

    /** F-2.7: penghapusan bersifat soft delete. */
    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);
        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_HAPUS, $document);
        $document->delete();

        return redirect()->route('dokumen.index')->with('sukses', 'Dokumen dipindahkan ke arsip terhapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $document);
        $document->restore();
        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_PULIHKAN, $document);

        return redirect()->route('dokumen.index')->with('sukses', 'Dokumen dipulihkan.');
    }

    /** F-2.7: hanya admin yang dapat menghapus permanen. */
    public function forceDestroy(int $id): RedirectResponse
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $document);

        Storage::delete(array_filter([$document->file_path, $document->cover_path]));
        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_HAPUS, $document, ['permanen' => true]);
        $document->forceDelete();

        return redirect()->route('dokumen.index', ['terhapus' => 1])
            ->with('sukses', 'Dokumen dihapus permanen.');
    }

    /** F-2.10: memicu ulang pemrosesan dokumen yang gagal. */
    public function ingestUlang(Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        if (! $document->bolehDiprosesAi()) {
            return back()->withErrors(['status' => 'Dokumen terbatas tidak diproses AI.']);
        }

        $document->update([
            'status' => Document::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        $this->audit->catatDokumen(PencatatAudit::DOKUMEN_INGEST_ULANG, $document);
        ProsesIngestDokumen::dispatch($document->id);

        return back()->with('sukses', 'Pemrosesan ulang dimasukkan ke antrean.');
    }

    /**
     * @return array{metadata: array<string, mixed>, tag: string}
     */
    private function validasi(Request $request, bool $wajibBerkas): array
    {
        $maksKb = config('pustakaai.unggah.maks_mb') * 1024;

        $tervalidasi = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'category_id' => ['nullable', 'exists:categories,id'],
            // F-8.1: siapa yang boleh melihat -- dipilih per dokumen.
            'classification' => ['required', Rule::in(array_keys(Document::SEMUA_KLASIFIKASI))],
            // F-8.2: boleh-tidaknya isi dokumen dibaca AI -- urusan terpisah.
            'ai_allowed' => ['required', 'boolean'],
            'tanggal_terbit' => ['nullable', 'date'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:10'],
            'abstract' => ['nullable', 'string', 'max:5000'],
            'tag' => ['nullable', 'string', 'max:500'],
            'berkas' => [$wajibBerkas ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:'.$maksKb],
            // F-8.3: kotak centang konfirmasi klasifikasi
            'konfirmasi_klasifikasi' => ['accepted'],
        ], [
            'title.required' => 'Judul dokumen wajib diisi.',
            'classification.required' => 'Pilih dulu siapa yang boleh melihat dokumen ini.',
            'ai_allowed.required' => 'Pilih dulu apakah dokumen boleh diproses AI.',
            'berkas.required' => 'Berkas PDF wajib diunggah.',
            'berkas.mimes' => 'Berkas harus berformat PDF.',
            'berkas.max' => 'Ukuran berkas melebihi '.config('pustakaai.unggah.maks_mb').' MB.',
            'konfirmasi_klasifikasi.accepted' => 'Anda harus mengonfirmasi pilihan pemrosesan AI sebelum menyimpan.',
        ]);

        return [
            'metadata' => collect($tervalidasi)
                ->except(['tag', 'berkas', 'konfirmasi_klasifikasi'])
                ->all(),
            'tag' => (string) ($tervalidasi['tag'] ?? ''),
        ];
    }

    /**
     * NFR 6.2: memastikan berkas benar-benar PDF dengan memeriksa
     * angka ajaib di awal berkas, bukan sekadar percaya ekstensi.
     */
    private function pastikanPdfAsli(string $lintasan): void
    {
        $pegangan = fopen($lintasan, 'rb');
        $awal = fread($pegangan, 5);
        fclose($pegangan);

        if ($awal !== '%PDF-') {
            abort(422, 'Isi berkas bukan PDF yang sah.');
        }
    }

    /** @return array<int, int> */
    private function tagIds(string $daftar): array
    {
        return collect(explode(',', $daftar))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->unique()
            ->map(fn (string $nama) => Tag::firstOrCreate(
                ['slug' => Str::slug($nama)],
                ['name' => $nama],
            )->id)
            ->all();
    }
}
