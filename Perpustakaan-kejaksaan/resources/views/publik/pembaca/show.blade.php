@extends('layouts.publik')
@section('judul', 'Baca: '.$dokumen->title)
@section('tanpa-bungkus', true)
@section('tanpa-kepala', true)

@section('konten')
{{-- Setinggi layar penuh: bilah kepala dan kaki situs
     disembunyikan lewat 'tanpa-kepala' di atas. Memakai h-dvh,
     bukan h-screen, karena di Safari seluler 100vh ikut menghitung
     bilah peramban sehingga dasar pembaca terpotong. --}}
<div class="flex h-dvh flex-col">

    <div class="border-b border-slate-200 bg-white px-4 py-2 shadow-sm">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2">

            <a href="{{ route('publik.katalog.show', $dokumen) }}"
               title="Kembali ke detail dokumen" aria-label="Kembali ke detail dokumen"
               class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-korps-700">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
                </svg>
                <span class="hidden sm:inline">Kembali</span>
            </a>

            <h1 class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800" title="{{ $dokumen->title }}">
                {{ $dokumen->title }}
            </h1>

            <x-lencana-klasifikasi :dokumen="$dokumen" />

            <div class="flex items-center gap-1">
                <button id="tombol-sebelumnya" type="button" title="Halaman sebelumnya"
                        class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <input id="input-halaman" type="number" min="1" value="1"
                       class="w-16 rounded-md border-slate-300 py-1 text-center text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                <span class="text-sm text-slate-500">/ <span id="total-halaman">?</span></span>
                <button id="tombol-berikutnya" type="button" title="Halaman berikutnya"
                        class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <div class="flex items-center gap-1">
                <button id="tombol-perkecil" type="button" title="Perkecil"
                        class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100">&minus;</button>
                <span id="label-zoom" class="w-12 text-center text-xs text-slate-500">130%</span>
                <button id="tombol-perbesar" type="button" title="Perbesar"
                        class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100">+</button>
            </div>

            <div class="flex items-center gap-2">
                <input id="input-cari" type="search" placeholder="Cari di dokumen…"
                       class="w-40 rounded-md border-slate-300 py-1 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:w-52">
            </div>

            <a href="{{ route('masuk') }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-korps-300 bg-korps-50 px-3 py-1.5 text-sm font-medium text-korps-800 hover:bg-korps-100">
                Masuk untuk tanya AI
            </a>
        </div>

        <div id="hasil-cari" class="mx-auto mt-1 max-w-7xl text-xs empty:hidden"></div>
    </div>

    <div class="flex-1 overflow-auto bg-slate-200 px-4 py-6">
        <div id="wadah-halaman" class="mx-auto w-fit"></div>
        <p id="status-pembaca" class="py-12 text-center text-sm text-slate-600"></p>
    </div>
</div>
@endsection

@push('skrip')
<script type="module">
    window.PustakaAI.pasangPembaca({
        urlBerkas: @json(route('publik.berkas.lihat', $dokumen)),
        urlPenanda: null,
        halamanAwal: 1,
        csrf: @json(csrf_token()),
        {{-- Dekoder WebAssembly PDF.js -- wajib agar dokumen hasil pindaian
             (gambar JBIG2/JPEG 2000) tidak tampil sebagai halaman kosong. --}}
        urlWasm: @json(asset('pdfjs-wasm/')),
    });
</script>
@endpush
