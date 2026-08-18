@extends('layouts.app')
@section('judul', 'Katalog')

@section('konten')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Katalog Dokumen</h1>
        <p class="mt-1 text-sm text-slate-500">
            Cari berdasarkan judul, penulis, nomor dokumen, atau isi dokumen.
        </p>
    </div>

    {{-- F-3.1 s.d. F-3.5: pencarian, filter, dan pengurutan --}}
    <form method="GET" action="{{ route('katalog.index') }}"
          class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                {{-- Ikon dipusatkan lewat pembungkus flex setinggi penuh kotak
                     masukan (bukan top-1/2 + translate), supaya sejajar
                     persis dengan cara peramban memusatkan teks placeholder
                     -- teknik yang sama menghindari selisih sub-piksel yang
                     membuat ikon kaca pembesar terlihat sedikit turun. --}}
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <input type="search" name="q" value="{{ $kueri }}"
                       placeholder="Kata kunci, mis. tindak pidana korupsi"
                       class="w-full rounded-lg border-slate-300 pl-9 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
            </div>
            <button type="submit"
                    class="rounded-lg bg-korps-700 px-5 py-2 text-sm font-semibold text-white hover:bg-korps-800 transition">
                Cari
            </button>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-5">
            <select name="kategori" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                <option value="">Semua kategori</option>
                @foreach ($kategoriSemua as $kategori)
                    <option value="{{ $kategori->id }}" @selected(request('kategori') == $kategori->id)>
                        {{ $kategori->name }}
                    </option>
                @endforeach
            </select>

            <select name="tahun" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                <option value="">Semua tahun</option>
                @foreach ($tahunSemua as $tahun)
                    <option value="{{ $tahun }}" @selected(request('tahun') == $tahun)>{{ $tahun }}</option>
                @endforeach
            </select>

            <select name="klasifikasi" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                <option value="">Semua klasifikasi</option>
                @foreach (auth()->user()->klasifikasiYangBolehDilihat() as $k)
                    <option value="{{ $k }}" @selected(request('klasifikasi') === $k)>
                        {{ \App\Models\Document::SEMUA_KLASIFIKASI[$k] }}
                    </option>
                @endforeach
            </select>

            <input type="text" name="penulis" value="{{ request('penulis') }}" placeholder="Penulis"
                   class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">

            <select name="urut" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                <option value="relevansi" @selected($urutan === 'relevansi')>Paling relevan</option>
                <option value="terbaru" @selected($urutan === 'terbaru')>Terbaru</option>
                <option value="judul" @selected($urutan === 'judul')>Judul A-Z</option>
                <option value="tahun" @selected($urutan === 'tahun')>Tahun terbaru</option>
            </select>
        </div>
    </form>

    <div class="mb-4 flex items-baseline justify-between">
        <p class="text-sm text-slate-500">
            {{ number_format($dokumen->total()) }} dokumen ditemukan
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
           class="group flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-korps-600 hover:shadow-md">
            <div class="flex gap-4 p-4">
                {{-- F-2.11: sampul otomatis dari halaman pertama --}}
                <div class="h-28 w-20 shrink-0 overflow-hidden rounded-md bg-slate-100 ring-1 ring-slate-200">
                    @if ($item->cover_path)
                        <img src="{{ route('berkas.sampul', $item) }}" alt=""
                             class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center text-slate-300">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <h2 class="line-clamp-2 font-semibold text-slate-900 group-hover:text-korps-700">
                        {{ $item->title }}
                    </h2>
                    <p class="mt-1 line-clamp-1 text-sm text-slate-500">
                        {{ $item->author ?: 'Penulis tidak dicantumkan' }}
                        @if ($item->year) &middot; {{ $item->year }} @endif
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <x-lencana-klasifikasi :dokumen="$item" />
                        @if ($item->category)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                {{ $item->category->name }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-auto flex items-center justify-between border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                <span>{{ $item->page_count ? $item->page_count.' halaman' : $item->ukuranTerbaca() }}</span>
                @if ($item->siapDitanya())
                    <span class="inline-flex items-center gap-1 font-medium text-violet-700">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 1.5l1.6 4.3 4.4 1.6-4.4 1.6L10 13.3 8.4 9 4 7.4l4.4-1.6L10 1.5z"/>
                        </svg>
                        Bisa ditanya
                    </span>
                @elseif ($item->classification === \App\Models\Document::KLASIFIKASI_TERBATAS)
                    <span class="text-slate-400">Katalog saja</span>
                @endif
            </div>
        </a>

        @if ($loop->last)
            </div>
        @endif
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <p class="font-medium text-slate-700">Tidak ada dokumen yang cocok.</p>
            <p class="mt-1 text-sm text-slate-500">Coba kata kunci lain atau longgarkan filternya.</p>
        </div>
    @endforelse

    <div class="mt-6">
        {{ $dokumen->links() }}
    </div>
@endsection
