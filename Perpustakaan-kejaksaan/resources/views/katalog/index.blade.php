@extends('layouts.app')
@section('judul', 'Katalog')
@section('tanpa-bungkus', true)

@php
    $pengguna = auth()->user();
    $klasifikasiBoleh = $pengguna->klasifikasiYangBolehDilihat();
@endphp

@section('konten')

{{-- Susunan beranda mengikuti jalur publik (kop surat, bilah pencarian,
     rak dokumen terbaru, telusur kategori) supaya pegawai yang terbiasa
     dengan katalog publik tidak perlu belajar tata letak baru. Yang
     berbeda hanya kewenangannya: dokumen internal ikut tampil, filter
     klasifikasi tersedia, dan penanda "Bisa ditanya" (AI) muncul pada
     dokumen yang sudah siap. --}}
<section class="relative overflow-hidden bg-paper-50">
    <img src="{{ asset('images/logo-kejaksaan.png') }}" alt=""
         class="pointer-events-none absolute left-1/2 top-1/2 h-[36rem] w-[36rem] -translate-x-1/2 -translate-y-1/2 select-none opacity-[0.05] grayscale">

    {{-- Ukuran kop (jarak, tinggi lambang, ukuran tajuk) sengaja identik
         dengan jalur publik di publik/katalog/index.blade.php, supaya
         pegawai yang berpindah dari katalog publik ke katalog internal
         tidak merasakan pergeseran skala halaman. --}}
    <div class="relative mx-auto max-w-3xl px-4 pt-14 pb-8 text-center sm:px-6 sm:pt-20">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brass-700">
            Kejaksaan Republik Indonesia &middot; Satya Adhi Wicaksana
        </p>
        <img src="{{ asset('images/logo-kejaksaan.png') }}" alt="Lambang Kejaksaan Republik Indonesia"
             width="72" height="74" class="mx-auto mt-5 h-16 w-auto object-contain">
        <h1 class="mt-5 font-serif text-3xl font-semibold tracking-tight text-korps-900 sm:text-5xl">
            Katalog Dokumen
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">
            Cari berdasarkan judul, penulis, tag, atau nomor dokumen &mdash;
            termasuk koleksi internal. Dokumen yang sudah diproses dapat ditanyakan
            langsung ke asisten AI.
        </p>

        {{-- F-8.4/F-8.5: pegawai perlu tahu batas aksesnya sendiri, bukan
             menebak kenapa suatu dokumen tidak muncul di hasil pencarian. --}}
        <p class="mt-4 inline-flex flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-full border border-korps-100 bg-white px-4 py-1.5 text-xs text-slate-600">
            <span class="font-semibold uppercase tracking-wide text-brass-700">Akses Anda</span>
            <span aria-hidden="true" class="text-slate-300">|</span>
            <span>{{ $pengguna->namaPeran() }}</span>
            <span aria-hidden="true" class="text-slate-300">|</span>
            <span>Klasifikasi
                {{ implode(', ', array_map(fn ($k) => \App\Models\Document::SEMUA_KLASIFIKASI[$k], $klasifikasiBoleh)) }}
            </span>
        </p>
    </div>

    {{-- Garis kop surat: satu tebal, satu tipis -- konvensi kepala surat dinas. --}}
    <div class="relative mx-auto max-w-5xl px-4 sm:px-6" aria-hidden="true">
        <div class="h-[3px] bg-korps-800"></div>
        <div class="mt-1 h-px bg-brass-600"></div>
    </div>

    {{-- F-3.1 s.d. F-3.5: pencarian, filter, dan pengurutan. Hanya filter
         Klasifikasi yang tidak ada di jalur publik. --}}
    {{-- Beranda cukup satu kotak pencarian; penyaring lengkap ada di
         halaman "Semua Dokumen" tempat hasilnya ditampilkan. --}}
    <form method="GET" action="{{ route('katalog.semua') }}"
          class="relative mx-auto max-w-5xl px-4 pt-8 pb-12 sm:px-6">
        <div class="rounded-lg border border-korps-100 bg-white p-6 shadow-sm sm:p-8">
            <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                <div>
                    <label for="cari-kueri" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Kata kunci
                    </label>
                    <input type="search" name="q" id="cari-kueri"
                           placeholder="Judul, penulis, tag, atau nomor dokumen…"
                           class="w-full rounded-md border-slate-300 py-3.5 text-lg shadow-sm focus:border-korps-600 focus:ring-korps-600 text-slate-900">
                </div>

                <button type="submit"
                        class="flex items-center justify-center gap-2 self-end rounded-md bg-korps-700 px-10 py-3.5 text-lg font-semibold text-white hover:bg-korps-800 transition">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                    </svg>
                    Cari
                </button>
            </div>

            <p class="mt-4 border-t border-paper-200 pt-3 text-xs text-slate-500">
                Butuh penyaring kategori, tahun, atau pengurutan?
                <a href="{{ route('katalog.semua') }}" class="font-medium text-korps-700 hover:underline">Buka daftar lengkap</a>.
            </p>
        </div>
    </form>
</section>

{{-- Rak depan katalog -- hanya selagi belum mencari/menyaring. --}}
@if ($dokumenTerbaru->isNotEmpty())
    <section class="border-y border-paper-200 bg-paper-100 py-10 sm:py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <h2 class="font-serif text-xl font-semibold text-korps-900 sm:text-2xl">Dokumen Terbaru</h2>
                <a href="{{ route('katalog.semua') }}" class="shrink-0 text-sm font-medium text-korps-700 hover:underline">
                    Lihat semua &raquo;
                </a>
            </div>

            <div class="relative">
                <div id="carousel-terbaru" class="flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($dokumenTerbaru as $item)
                        <a href="{{ route('katalog.show', $item) }}"
                           class="group flex w-80 shrink-0 snap-start flex-col overflow-hidden rounded-lg border border-paper-200 bg-white transition hover:border-korps-700 hover:shadow-md sm:w-96">
                            <div class="flex flex-1 gap-4 p-4">
                                <div class="h-32 w-24 shrink-0 overflow-hidden rounded-md bg-paper-100 ring-1 ring-paper-200">
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
                                    @if ($item->category)
                                        <span class="inline-flex w-fit items-center rounded border border-korps-100 bg-paper-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-korps-800">
                                            {{ $item->category->name }}
                                        </span>
                                    @endif
                                    <h3 class="mt-1.5 line-clamp-2 font-serif text-base font-semibold text-korps-900 group-hover:text-korps-700">
                                        {{ $item->title }}
                                    </h3>
                                    <p class="mt-1 line-clamp-1 text-sm text-slate-500">
                                        {{ $item->author ?: 'Penulis tidak dicantumkan' }}
                                        @if ($item->year) &middot; {{ $item->year }} @endif
                                    </p>
                                    {{-- Koleksi internal ikut tampil di sini, jadi
                                         klasifikasi perlu terbaca sejak di rak. --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-sm">
                                        <x-lencana-klasifikasi :dokumen="$item" />
                                        <x-lencana-dilihat :dokumen="$item" />
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-t border-paper-200 px-4 py-3 text-sm">
                                <span class="font-mono text-xs text-slate-500">{{ $item->page_count ? $item->page_count.' hlm.' : $item->ukuranTerbaca() }}</span>
                                @if ($item->siapDitanya())
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-violet-700">
                                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10 1.5l1.6 4.3 4.4 1.6-4.4 1.6L10 13.3 8.4 9 4 7.4l4.4-1.6L10 1.5z"/>
                                        </svg>
                                        Bisa ditanya
                                    </span>
                                @endif
                                <span class="font-medium text-korps-700">Lihat detail &rarr;</span>
                            </div>
                        </a>
                    @endforeach
                </div>

                {{-- Panah navigasi carousel -- geser secara native, tanpa modul JS terpisah. --}}
                <button type="button" aria-label="Sebelumnya"
                        onclick="document.getElementById('carousel-terbaru').scrollBy({left: -380, behavior: 'smooth'})"
                        class="absolute left-0 top-1/2 hidden -translate-x-4 -translate-y-1/2 rounded-full border border-paper-200 bg-white p-2.5 text-korps-800 shadow-md hover:border-korps-700 sm:flex">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/></svg>
                </button>
                <button type="button" aria-label="Berikutnya"
                        onclick="document.getElementById('carousel-terbaru').scrollBy({left: 380, behavior: 'smooth'})"
                        class="absolute right-0 top-1/2 hidden translate-x-4 -translate-y-1/2 rounded-full border border-paper-200 bg-white p-2.5 text-korps-800 shadow-md hover:border-korps-700 sm:flex">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                </button>
            </div>
        </div>
    </section>
@endif

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <x-notifikasi />

    {{-- Dokumen yang banyak dicari -- diukur dari berapa ORANG berbeda yang
         pernah membukanya, penghitung yang sudah dipakai lencana "Dilihat". --}}
    @if ($dokumenBanyakDilihat->isNotEmpty())
        <div class="mb-10">
            <h2 class="mb-4 font-serif text-lg font-semibold text-korps-900">Dokumen yang Banyak Dicari</h2>
            <ol class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($dokumenBanyakDilihat as $item)
                    <li>
                        <a href="{{ route('katalog.show', $item) }}"
                           class="group flex items-start gap-3 rounded-md border border-paper-200 bg-white px-4 py-3 transition hover:border-korps-700 hover:shadow-sm">
                            <span class="mt-0.5 shrink-0 font-mono text-xs font-semibold text-brass-700">{{ $loop->iteration }}.</span>
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-2 block font-serif text-sm font-semibold text-korps-900 group-hover:text-korps-700">
                                    {{ $item->title }}
                                </span>
                                <span class="mt-1 block text-xs text-slate-500">
                                    <x-lencana-dilihat :dokumen="$item" />
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Telusuri per kategori -- bilah kuningan kiri seperti label laci
         katalog kartu. Kategori internal ikut terhitung di sini. --}}
    @if ($kategoriUtama->isNotEmpty())
        <div class="mb-10">
            <h2 class="mb-4 font-serif text-lg font-semibold text-korps-900">Telusuri per Kategori</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($kategoriUtama as $kategori)
                    <a href="{{ route('katalog.semua', ['kategori' => $kategori->id]) }}"
                       class="flex items-center justify-between gap-3 rounded-md border border-paper-200 border-l-4 border-l-brass-600 bg-white px-4 py-3.5 transition hover:border-korps-700 hover:border-l-korps-700 hover:shadow-sm">
                        <span class="min-w-0 font-serif font-medium text-korps-900">{{ $kategori->name }}</span>
                        <span class="shrink-0 rounded-full bg-paper-100 px-2.5 py-1 font-mono text-xs text-slate-600">
                            {{ $kategori->documents_count }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

@endsection
