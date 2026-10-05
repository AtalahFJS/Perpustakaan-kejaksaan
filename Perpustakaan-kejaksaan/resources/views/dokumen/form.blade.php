@extends('layouts.app')
@section('judul', $dokumen->exists ? 'Ubah Dokumen' : 'Unggah Dokumen')

@section('konten')
    <div class="mx-auto max-w-3xl">
        <x-tautan-kembali :href="route('dokumen.index')">Kembali ke Kelola Dokumen</x-tautan-kembali>

        <h1 class="mb-6 text-2xl font-semibold text-slate-900">
            {{ $dokumen->exists ? 'Ubah Dokumen' : 'Unggah Dokumen Baru' }}
        </h1>

        <form method="POST" enctype="multipart/form-data"
              action="{{ $dokumen->exists ? route('dokumen.update', $dokumen) : route('dokumen.store') }}"
              class="space-y-5">
            @csrf
            @if ($dokumen->exists) @method('PUT') @endif

            {{-- Berkas --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Berkas</h2>

                @if ($dokumen->exists)
                    <p class="mb-2 text-sm text-slate-600">
                        Berkas saat ini: <span class="font-medium">{{ $dokumen->file_name }}</span>
                        <span class="text-slate-400">({{ $dokumen->ukuranTerbaca() }})</span>
                    </p>
                    <p class="mb-2 text-xs text-slate-500">
                        Kosongkan bila tidak ingin mengganti berkas. Mengganti berkas akan memicu pemrosesan ulang.
                    </p>
                @endif

                <input type="file" name="berkas" accept="application/pdf"
                       @required(! $dokumen->exists)
                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-korps-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-korps-700 hover:file:bg-korps-100">
                <p class="mt-1.5 text-xs text-slate-500">
                    Format PDF, maksimum {{ config('pustakaai.unggah.maks_mb') }} MB.
                </p>
                @error('berkas') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </section>

            {{-- Metadata wajib (F-2.3) --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Metadata wajib</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="title" class="block text-sm font-medium text-slate-700">Judul <span class="text-rose-600">*</span></label>
                        <input id="title" name="title" type="text" required value="{{ old('title', $dokumen->title) }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                        @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="author" class="block text-sm font-medium text-slate-700">Penulis</label>
                        <input id="author" name="author" type="text" value="{{ old('author', $dokumen->author) }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                    </div>

                    <div>
                        <label for="publisher" class="block text-sm font-medium text-slate-700">Penerbit</label>
                        <input id="publisher" name="publisher" type="text" value="{{ old('publisher', $dokumen->publisher) }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                    </div>

                    <div>
                        <label for="tanggal_terbit" class="block text-sm font-medium text-slate-700">Tanggal terbit</label>
                        <input id="tanggal_terbit" name="tanggal_terbit" type="date"
                               value="{{ old('tanggal_terbit', $dokumen->tanggal_terbit?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                        <p class="mt-1 text-xs text-slate-500">
                            Dipakai mengurutkan &ldquo;Dokumen Terbaru&rdquo; di katalog.
                        </p>
                        @error('tanggal_terbit') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="year" class="block text-sm font-medium text-slate-700">Tahun</label>
                        <input id="year" name="year" type="number" min="1900" max="{{ date('Y') + 1 }}"
                               value="{{ old('year', $dokumen->year) }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                        @error('year') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <label for="category_id" class="block text-sm font-medium text-slate-700">Kategori</label>
                            {{-- type="button": panel ini berada di dalam <form> unggah,
                                 jadi tombolnya tidak boleh ikut mengirim formulir. --}}
                            {{-- Ikon menandai keadaan panel: "+" saat tertutup,
                                 panah ke bawah saat terbuka. Pergantiannya
                                 disetir aria-expanded yang sudah dikelola JS,
                                 jadi penanda visual dan status bagi pembaca
                                 layar tidak mungkin berbeda. --}}
                            <button type="button" id="kategori-cepat-pemicu"
                                    aria-expanded="false" aria-controls="kategori-cepat-panel"
                                    class="group inline-flex items-center gap-1 text-xs font-semibold text-korps-700 hover:underline">
                                <svg class="h-3.5 w-3.5 shrink-0 group-aria-expanded:hidden" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M10 4.25a.75.75 0 01.75.75v4.25H15a.75.75 0 010 1.5h-4.25V15a.75.75 0 01-1.5 0v-4.25H5a.75.75 0 010-1.5h4.25V5a.75.75 0 01.75-.75z"/>
                                </svg>
                                <svg class="hidden h-3.5 w-3.5 shrink-0 group-aria-expanded:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                                Tambah kategori
                            </button>
                        </div>
                        <select id="category_id" name="category_id"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                            <option value="">— pilih —</option>
                            @foreach ($kategoriSemua as $kategori)
                                <option value="{{ $kategori->id }}" @selected(old('category_id', $dokumen->category_id) == $kategori->id)>
                                    {{ $kategori->name }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Panel tambah kategori. Sengaja <div>, bukan <form>:
                             formulir bersarang tidak sah di HTML. Kategori
                             dikirim lewat fetch() supaya berkas PDF yang sudah
                             dipilih tidak hilang -- lihat resources/js/kategori-cepat.js. --}}
                        <div id="kategori-cepat-panel" hidden
                             class="mt-2 rounded-lg border border-korps-100 bg-korps-50/60 p-3">
                            <label for="kategori-cepat-nama" class="block text-xs font-medium text-slate-700">
                                Nama kategori baru
                            </label>
                            <input id="kategori-cepat-nama" type="text" maxlength="255"
                                   placeholder="mis. Surat Edaran"
                                   class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">


                            <p id="kategori-cepat-galat" hidden role="alert" class="mt-2 text-xs text-rose-600"></p>

                            <div class="mt-3 flex items-center gap-3">
                                <button type="button" id="kategori-cepat-simpan"
                                        class="rounded-lg bg-korps-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-korps-800 disabled:opacity-60">
                                    Simpan kategori
                                </button>
                                <button type="button" id="kategori-cepat-batal"
                                        class="text-xs font-medium text-slate-600 hover:text-slate-900">
                                    Batal
                                </button>
                            </div>
                        </div>

                        <p class="mt-2 text-xs text-slate-500">
                            Untuk menyunting atau menghapus kategori, buka
                            <a href="{{ route('admin.kategori.index') }}" class="text-korps-700 hover:underline">Kelola Kategori</a>.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Akses dokumen (F-8.1). Sejak 26 Agustus 2026 kembali dipilih
                 per dokumen; kategori tidak lagi menentukannya. --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-900">Akses dokumen <span class="text-rose-600">*</span></h2>
                <p class="mb-3 text-xs text-slate-500">
                    Menentukan siapa yang dapat melihat dokumen ini di katalog.
                    Tidak dipengaruhi kategori yang dipilih di atas.
                </p>

                <div class="space-y-2">
                    @foreach ([
                        \App\Models\Document::KLASIFIKASI_PUBLIK => ['Publik', 'Terlihat semua orang, termasuk pengunjung tanpa login.'],
                        \App\Models\Document::KLASIFIKASI_INTERNAL => ['Internal', 'Hanya pengguna yang sudah masuk — Pegawai dan Admin.'],
                    ] as $nilai => [$label, $penjelasan])
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 has-checked:border-korps-600 has-checked:bg-korps-50">
                            <input type="radio" name="classification" value="{{ $nilai }}" required
                                   @checked(old('classification', $dokumen->classification) === $nilai)
                                   class="mt-0.5 border-slate-300 text-korps-600 focus:ring-korps-600">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">{{ $label }}</span>
                                <span class="block text-xs text-slate-500">{{ $penjelasan }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('classification') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </section>

            {{-- Pemrosesan AI (F-8.2, F-8.3).

                 Bagian ini hanya mengurus satu hal: boleh atau tidak isi
                 dokumen dibaca asisten AI. Siapa yang dapat melihat dokumen
                 ditentukan visibilitas kategori pada bagian di atas, bukan
                 di sini -- dua urusan itu dulu tercampur dalam satu pilihan
                 dan membingungkan saat pengunggahan. --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-900">Pemrosesan AI <span class="text-rose-600">*</span></h2>
                <p class="mb-3 text-xs text-slate-500">
                    Menentukan apakah isi dokumen ini boleh dibaca asisten AI untuk ringkasan
                    dan tanya jawab. Tidak memengaruhi siapa yang dapat melihat dokumen &mdash;
                    itu mengikuti kategori yang dipilih di atas.
                </p>

                @php $bolehAiSekarang = old('ai_allowed', $dokumen->exists ? (int) $dokumen->ai_allowed : 1); @endphp
                <div class="space-y-2">
                    @foreach ([
                        1 => ['Boleh diproses AI', 'Isi dokumen dibaca asisten AI untuk membuat ringkasan dan menjawab pertanyaan.'],
                        0 => ['Tidak boleh diproses AI', 'Isi TIDAK PERNAH dikirim ke layanan AI. Dokumen tetap dapat dibaca manusia, hanya tanpa fitur AI.'],
                    ] as $nilai => [$label, $penjelasan])
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 has-checked:border-korps-600 has-checked:bg-korps-50">
                            <input type="radio" name="ai_allowed" value="{{ $nilai }}" required
                                   @checked((int) $bolehAiSekarang === $nilai)
                                   class="mt-0.5 border-slate-300 text-korps-600 focus:ring-korps-600">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">{{ $label }}</span>
                                <span class="block text-xs text-slate-500">{{ $penjelasan }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('ai_allowed') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror

                {{-- F-8.3: konfirmasi eksplisit sebelum ingest dimulai --}}
                <label class="mt-4 flex items-start gap-2.5 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    <input type="checkbox" name="konfirmasi_klasifikasi" value="1" required
                           class="mt-0.5 rounded border-amber-400 text-amber-600 focus:ring-amber-600">
                    <span>
                        Saya mengonfirmasi pilihan di atas sudah benar dan memahami konsekuensinya
                        terhadap pemrosesan dokumen ini oleh AI.
                    </span>
                </label>
                @error('konfirmasi_klasifikasi') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </section>

            {{-- Metadata opsional (F-2.4) --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Metadata opsional</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="document_number" class="block text-sm font-medium text-slate-700">Nomor dokumen</label>
                        <input id="document_number" name="document_number" type="text"
                               value="{{ old('document_number', $dokumen->document_number) }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                    </div>
                    <div>
                        <label for="isbn" class="block text-sm font-medium text-slate-700">ISBN</label>
                        <input id="isbn" name="isbn" type="text" value="{{ old('isbn', $dokumen->isbn) }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="tag" class="block text-sm font-medium text-slate-700">Tag</label>
                        <input id="tag" name="tag" type="text"
                               value="{{ old('tag', $dokumen->exists ? $dokumen->tags->pluck('name')->join(', ') : '') }}"
                               placeholder="pisahkan dengan koma, mis. korupsi, tipikor, pedoman"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="abstract" class="block text-sm font-medium text-slate-700">Abstrak</label>
                        <textarea id="abstract" name="abstract" rows="4"
                                  class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">{{ old('abstract', $dokumen->abstract) }}</textarea>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('dokumen.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Batal</a>
                <button type="submit"
                        class="rounded-lg bg-korps-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-korps-800 transition">
                    {{ $dokumen->exists ? 'Simpan Perubahan' : 'Unggah & Proses' }}
                </button>
            </div>
        </form>
    </div>
@endsection

@push('skrip')
    <script type="module">
        window.PustakaAI.pasangKategoriCepat({
            urlSimpan: @json(route('admin.kategori.simpan')),
            idPemicu: 'kategori-cepat-pemicu',
            idPanel: 'kategori-cepat-panel',
            idNama: 'kategori-cepat-nama',
            idSimpan: 'kategori-cepat-simpan',
            idBatal: 'kategori-cepat-batal',
            idGalat: 'kategori-cepat-galat',
            idPilihan: 'category_id',
        });
    </script>
@endpush
