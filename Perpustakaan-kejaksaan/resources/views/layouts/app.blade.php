{{-- NFR 6.5: antarmuka berbahasa Indonesia dan responsif (laptop & tablet). --}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Perpustakaan Digital') &mdash; {{ config('app.name') }}</title>

    {{-- Lambang Kejaksaan sebagai ikon situs --}}
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-paper-50 text-slate-800 antialiased">

<div class="min-h-full flex flex-col">
    {{-- Halaman pembaca dokumen menyalakan 'tanpa-kepala' agar bilah kepala
         dan kaki disembunyikan: layar terpakai penuh untuk dokumennya, dan
         tampilannya lebih bersih. Navigasi tidak hilang -- bilah alat
         pembaca punya tautan "Kembali" sendiri. --}}
    @unless (View::hasSection('tanpa-kepala'))
    <header class="border-b-2 border-brass-600 bg-korps-800 text-white shadow-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {{-- Tinggi bilah, ukuran lambang, dan ukuran nama lembaga dijaga
                 sama persis dengan layouts/publik.blade.php agar skala halaman
                 tidak melompat saat pegawai masuk atau keluar. --}}
            <div class="flex h-24 items-center justify-between gap-4">
                <a href="{{ route('katalog.index') }}" class="flex min-w-0 items-center gap-3 sm:gap-4">
                    {{-- Sumber dirender pada ~3x ukuran tampil supaya detail
                         halus lambang -- tiga lidah api, untaian padi -- tetap
                         tajam di layar Retina, bukan buram karena raster kecil
                         diperbesar. width/height eksplisit menjaga ukuran
                         sebelum CSS termuat, agar tata letak tidak bergeser. --}}
                    <img src="{{ asset('images/logo-kejaksaan-header.png') }}"
                         alt="Lambang Kejaksaan Republik Indonesia"
                         width="58" height="60"
                         class="h-11 w-auto shrink-0 object-contain sm:h-14">
                    <span class="min-w-0 truncate font-serif text-lg font-semibold tracking-tight sm:text-2xl">{{ config('app.name') }}</span>
                </a>

                @php
                    $r = request()->route()?->getName() ?? '';
                    $pengguna = auth()->user();
                    // Sejajar dengan bilah kepala jalur publik: seluruh
                    // navigasi dirangkum ke satu menu, sehingga tata letaknya
                    // sama baik sebelum maupun sesudah masuk, dan tidak lagi
                    // butuh baris navigasi terpisah untuk layar kecil.
                    $tautanMenu = collect([
                        ['Katalog', route('katalog.index'), str_starts_with($r, 'katalog')],
                    ])->when($pengguna->isAdmin(), fn ($t) => $t->push(
                        ['Kelola Dokumen', route('dokumen.index'), str_starts_with($r, 'dokumen')],
                        ['Administrasi', route('admin.dasbor'), str_starts_with($r, 'admin')],
                    ));
                @endphp

                <div class="flex shrink-0 items-center gap-3 sm:gap-4">
                    {{-- Identitas pengguna dijaga satu baris; bila ruang sempit
                         yang mengalah adalah nama lembaga di kiri (sudah
                         ber-truncate, dan tetap terbaca di kop halaman). --}}
                    <div class="hidden text-right leading-tight whitespace-nowrap sm:block">
                        <div class="text-sm font-medium">{{ $pengguna->name }}</div>
                        <div class="text-xs text-korps-100">{{ $pengguna->namaPeran() }}</div>
                    </div>

                    <x-menu-tarik-turun label="Menu">
                        {{-- Identitas diulang di dalam menu karena di layar
                             sempit baris di atas disembunyikan. --}}
                        <div class="border-b border-slate-100 px-3 pb-2 pt-1 sm:hidden">
                            <div class="text-sm font-medium text-slate-900">{{ $pengguna->name }}</div>
                            <div class="text-xs text-slate-500">{{ $pengguna->namaPeran() }}</div>
                        </div>

                        @foreach ($tautanMenu as [$label, $tautan, $aktif])
                            <a href="{{ $tautan }}" @if ($aktif) aria-current="page" @endif
                               class="mt-1 flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
                                      {{ $aktif ? 'bg-korps-50 text-korps-800' : 'text-slate-800 hover:bg-korps-50 hover:text-korps-700' }}">
                                {{ $label }}
                                @if ($aktif)
                                    <span class="text-xs font-normal text-korps-700">Sedang dibuka</span>
                                @endif
                            </a>
                        @endforeach

                        <form method="POST" action="{{ route('keluar') }}" class="mt-1 border-t border-slate-100 pt-1">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-rose-700 hover:bg-rose-50">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/>
                                    <path fill-rule="evenodd" d="M6 10a.75.75 0 01.75-.75h9.546l-1.048-.943a.75.75 0 111.004-1.114l2.5 2.25a.75.75 0 010 1.114l-2.5 2.25a.75.75 0 11-1.004-1.114l1.048-.943H6.75A.75.75 0 016 10z" clip-rule="evenodd"/>
                                </svg>
                                Keluar
                            </button>
                        </form>
                    </x-menu-tarik-turun>
                </div>
            </div>
        </div>
    </header>
    @endunless

    <main class="flex-1 @yield('lebar-penuh') ">
        @hasSection('tanpa-bungkus')
            @yield('konten')
        @else
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                <x-notifikasi />
                @yield('konten')
            </div>
        @endif
    </main>

    @unless (View::hasSection('tanpa-kepala'))
    <footer class="border-t border-brass-600/60 bg-paper-100">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 text-xs text-slate-500 flex flex-wrap items-center gap-x-4 gap-y-1 justify-between">
            <span class="flex items-center gap-2">
                <img src="{{ asset('images/logo-kejaksaan-96.png') }}"
                     alt="" aria-hidden="true"
                     width="20" height="20"
                     class="h-5 w-auto shrink-0 object-contain opacity-70">
                {{ config('app.name') }} &mdash; Sistem Perpustakaan Digital dengan Asisten AI
            </span>
            <span>Jawaban AI perlu diverifikasi ke dokumen asli.</span>
        </div>
    </footer>
    @endunless
</div>

{{-- Asisten mengambang hanya di halaman katalog perpustakaan: beranda
     katalog, daftar semua dokumen, dan detail dokumen.

     Sengaja TIDAK muncul di Administrasi maupun Kelola Dokumen -- di sana
     admin sedang mengurus data, bukan mencari bacaan, sehingga tombolnya
     hanya menutupi antarmuka. Halaman pembaca juga dikecualikan lewat
     'tanpa-kepala' karena sengaja dibuat bersih tanpa elemen menempel. --}}
@if (Route::is('katalog.*') && ! View::hasSection('tanpa-kepala'))
    <x-asisten-mengambang />
@endif

@stack('skrip')
</body>
</html>
