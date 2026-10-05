@extends('layouts.app')
@section('judul', 'Semua Dokumen')

@section('konten')
    <x-tautan-kembali :href="route('katalog.index')">Kembali ke Beranda Katalog</x-tautan-kembali>

    <h1 class="mb-1 font-serif text-2xl font-semibold text-korps-900">Semua Dokumen</h1>
    <p class="mb-6 text-sm text-slate-500">
        Telusuri seluruh koleksi yang dapat Anda akses, lengkap dengan penyaring
        dan pengurutan.
    </p>

    {{-- Bilah pencarian sama dengan yang di beranda, supaya kata kunci yang
         sudah diketik di sana tinggal diteruskan di halaman ini. --}}
    <form method="GET" action="{{ route('katalog.semua') }}"
          class="mb-6 rounded-lg border border-korps-100 bg-white p-5 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
            <div>
                <label for="cari-kueri" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Kata kunci
                </label>
                <input type="search" name="q" id="cari-kueri" value="{{ $kueri }}"
                       placeholder="Judul, penulis, tag, atau nomor dokumen…"
                       class="w-full rounded-md border-slate-300 py-3 shadow-sm focus:border-korps-600 focus:ring-korps-600 text-slate-900">
            </div>
            <button type="submit"
                    class="flex items-center justify-center gap-2 self-end rounded-md bg-korps-700 px-8 py-3 font-semibold text-white hover:bg-korps-800 transition">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                </svg>
                Cari
            </button>
        </div>

        <div class="mt-5 grid gap-4 border-t border-paper-200 pt-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="cari-kategori" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kategori</label>
                <select name="kategori" id="cari-kategori" class="h-10 w-full rounded-md border-slate-300 py-0 text-base shadow-sm focus:border-korps-600 focus:ring-korps-600 text-slate-900">
                    <option value="">Semua kategori</option>
                    @foreach ($kategoriSemua as $kategori)
                        <option value="{{ $kategori->id }}" @selected(request('kategori') == $kategori->id)>
                            {{ $kategori->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cari-tahun" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun</label>
                <select name="tahun" id="cari-tahun" class="h-10 w-full rounded-md border-slate-300 py-0 text-base shadow-sm focus:border-korps-600 focus:ring-korps-600 text-slate-900">
                    <option value="">Semua tahun</option>
                    @foreach ($tahunSemua as $tahun)
                        <option value="{{ $tahun }}" @selected(request('tahun') == $tahun)>{{ $tahun }}</option>
                    @endforeach
                </select>
            </div>

                <div>
                    <label for="cari-klasifikasi" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Klasifikasi</label>
                    <select name="klasifikasi" id="cari-klasifikasi" class="h-10 w-full rounded-md border-slate-300 py-0 text-base shadow-sm focus:border-korps-600 focus:ring-korps-600 text-slate-900">
                        <option value="">Semua klasifikasi</option>
                        @foreach (auth()->user()->klasifikasiYangBolehDilihat() as $k)
                            <option value="{{ $k }}" @selected(request('klasifikasi') === $k)>
                                {{ \App\Models\Document::SEMUA_KLASIFIKASI[$k] }}
                            </option>
                        @endforeach
                    </select>
                </div>

            <div>
                <label for="cari-urut" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Urutkan</label>
                <select name="urut" id="cari-urut" class="h-10 w-full rounded-md border-slate-300 py-0 text-base shadow-sm focus:border-korps-600 focus:ring-korps-600 text-slate-900">
                    <option value="relevansi" @selected($urutan === 'relevansi')>Paling relevan</option>
                    <option value="terbaru" @selected($urutan === 'terbaru')>Terbaru</option>
                    <option value="judul" @selected($urutan === 'judul')>Judul A-Z</option>
                    <option value="tahun" @selected($urutan === 'tahun')>Tahun terbaru</option>
                </select>
            </div>
        </div>

        @if ($sedangMenyaring)
            <div class="mt-4">
                <a href="{{ route('katalog.semua') }}" class="text-sm font-medium text-korps-700 hover:underline">
                    Hapus filter
                </a>
            </div>
        @endif
    </form>

    {{-- Seluruh dokumen -- hasil pencarian/filter, atau daftar lengkap. --}}
    <div id="semua-dokumen" class="scroll-mt-20">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-serif text-lg font-semibold text-korps-900">
                {{ $sedangMenyaring ? 'Hasil Pencarian' : 'Semua Dokumen' }}
            </h2>
            <p class="text-sm text-slate-500">
                {{ number_format($dokumen->total()) }} dokumen
                @if ($kueri !== '')
                    untuk <span class="font-medium text-slate-700">&ldquo;{{ $kueri }}&rdquo;</span>
                @endif
            </p>
        </div>

        @forelse ($dokumen as $item)
            @if ($loop->first)
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @endif

            <a href="{{ route('katalog.show', $item) }}"
               class="group flex flex-col overflow-hidden rounded-lg border border-paper-200 bg-white transition hover:border-korps-700 hover:shadow-md">
                <div class="flex gap-4 p-4">
                    {{-- F-2.11: sampul otomatis dari halaman pertama --}}
                    <div class="h-28 w-20 shrink-0 overflow-hidden rounded-md bg-paper-100 ring-1 ring-paper-200">
                        @if ($item->cover_path)
                            <img src="{{ route('berkas.sampul', $item) }}" alt=""
                                 class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-korps-100">
                                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                </svg>
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <span class="font-mono text-[11px] tracking-wide text-brass-700">
                            No. Aksesi {{ str_pad($item->id, 6, '0', STR_PAD_LEFT) }}
                        </span>
                        <h2 class="mt-0.5 line-clamp-2 font-serif font-semibold text-korps-900 group-hover:text-korps-700">
                            {{ $item->title }}
                        </h2>
                        <p class="mt-1 line-clamp-1 text-sm text-slate-500">
                            {{ $item->author ?: 'Penulis tidak dicantumkan' }}
                            @if ($item->year) &middot; {{ $item->year }} @endif
                        </p>

                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <x-lencana-klasifikasi :dokumen="$item" />
                            @if ($item->category)
                                <span class="rounded border border-korps-100 bg-paper-50 px-2 py-0.5 text-xs text-korps-800">
                                    {{ $item->category->name }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-paper-200 px-4 py-2 text-xs text-slate-500">
                    <span class="font-mono">{{ $item->page_count ? $item->page_count.' hlm.' : $item->ukuranTerbaca() }}</span>
                    <x-lencana-dilihat :dokumen="$item" />

                    {{-- F-8.2: dokumen terbatas tidak pernah diproses AI, jadi
                         ketersediaan tanya-jawab ditandai eksplisit di sini
                         agar pegawai tidak membuka detail hanya untuk tahu. --}}
                    @if ($item->siapDitanya())
                        <span class="ml-auto inline-flex items-center gap-1 font-medium text-violet-700">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 1.5l1.6 4.3 4.4 1.6-4.4 1.6L10 13.3 8.4 9 4 7.4l4.4-1.6L10 1.5z"/>
                            </svg>
                            Bisa ditanya
                        </span>
                    @elseif (! $item->bolehDiprosesAi())
                        <span class="ml-auto text-slate-400">Katalog saja</span>
                    @endif
                </div>
            </a>

            @if ($loop->last)
                </div>
            @endif
        @empty
            <div class="rounded-lg border border-dashed border-paper-200 bg-white px-6 py-16 text-center">
                <p class="font-serif font-medium text-korps-900">Tidak ada dokumen yang cocok.</p>
                <p class="mt-1 text-sm text-slate-500">Coba kata kunci lain atau longgarkan filternya.</p>
            </div>
        @endforelse

        <div class="mt-6">
            {{ $dokumen->links() }}
        </div>
    </div>
</div>
@endsection
