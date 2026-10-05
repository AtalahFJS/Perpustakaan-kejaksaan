@extends('layouts.publik')
@section('judul', 'Selamat Datang')
@section('tanpa-bungkus', true)
@section('tanpa-kepala', true)

@section('konten')
{{-- Gerbang sebelum katalog: pengunjung memilih jalur masuknya. Bilah
     kepala situs sengaja disembunyikan agar pilihannya menjadi satu-satunya
     hal di layar dan tidak ada jalan pintas melewatinya. --}}
<div class="flex min-h-dvh flex-col items-center justify-center bg-paper-50 px-4 py-12">
    <img src="{{ asset('images/logo-kejaksaan.png') }}" alt=""
         class="pointer-events-none fixed left-1/2 top-1/2 h-[36rem] w-[36rem] -translate-x-1/2 -translate-y-1/2 select-none opacity-[0.05] grayscale">

    <div class="relative w-full max-w-2xl text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brass-700">
            Kejaksaan Republik Indonesia &middot; Satya Adhi Wicaksana
        </p>
        <img src="{{ asset('images/logo-kejaksaan.png') }}" alt="Lambang Kejaksaan Republik Indonesia"
             width="72" height="74" class="mx-auto mt-5 h-16 w-auto object-contain">
        <h1 class="mt-5 font-serif text-3xl font-semibold tracking-tight text-korps-900 sm:text-4xl">
            {{ config('app.name') }}
        </h1>
        {{-- Kalimat pengarah, bukan pertanyaan: pengunjung langsung tahu
             tombol mana yang harus ditekan tanpa menafsirkan dulu. --}}
        <p class="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-slate-600">
            Jika Anda masuk sebagai tamu, pilih <span class="font-semibold text-korps-800">Tamu</span>.
            Jika Anda pegawai atau admin, pilih <span class="font-semibold text-korps-800">Pegawai</span>.
        </p>

        <div class="mx-auto mt-8 h-[3px] max-w-md bg-korps-800" aria-hidden="true"></div>
        <div class="mx-auto mt-1 h-px max-w-md bg-brass-600" aria-hidden="true"></div>

        <div class="mt-8 grid gap-4 text-left sm:grid-cols-2">
            <a href="{{ route('buku-tamu.form') }}"
               class="group rounded-xl border border-paper-200 bg-white p-6 transition hover:border-korps-700 hover:shadow-md">
                <span class="font-serif text-xl font-semibold text-korps-900 group-hover:text-korps-700">Tamu</span>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    Pengunjung umum. Isi buku tamu sebentar, lalu telusuri dokumen
                    yang terbuka untuk publik.
                </p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-korps-700">
                    Isi buku tamu &rarr;
                </span>
            </a>

            <a href="{{ route('masuk') }}"
               class="group rounded-xl border border-paper-200 bg-white p-6 transition hover:border-korps-700 hover:shadow-md">
                <span class="font-serif text-xl font-semibold text-korps-900 group-hover:text-korps-700">Pegawai</span>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    Pegawai dan Admin. Masuk dengan akun untuk membuka koleksi
                    internal serta asisten tanya-jawab AI.
                </p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-korps-700">
                    Masuk &rarr;
                </span>
            </a>
        </div>
    </div>
</div>
@endsection
