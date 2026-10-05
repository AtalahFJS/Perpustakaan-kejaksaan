{{-- Tata letak untuk pengunjung tanpa login (pembaruan 21 Agustus 2026).
     Berbeda dari layouts.app: tidak berasumsi ada pengguna yang masuk,
     dan mengarahkan navigasi ke rute berawalan publik.*. --}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Katalog Publik') &mdash; {{ config('app.name') }}</title>

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
            <div class="flex h-24 items-center justify-between gap-4">
                <a href="{{ route('publik.katalog.index') }}" class="flex min-w-0 items-center gap-3 sm:gap-4">
                    <img src="{{ asset('images/logo-kejaksaan-header.png') }}"
                         alt="Lambang Kejaksaan Republik Indonesia"
                         width="58" height="60"
                         class="h-11 w-auto shrink-0 object-contain sm:h-14">
                    <span class="min-w-0 truncate font-serif text-lg font-semibold tracking-tight sm:text-2xl">{{ config('app.name') }}</span>
                </a>

                <div class="flex items-center gap-4">
                    <span class="hidden sm:inline text-sm text-korps-100">Katalog publik &mdash; sebagian data</span>

                    {{-- Menu "lainnya" -- menggantikan tombol Masuk tunggal, merentet
                         ke bawah berisi akses masuk pegawai dan info kontak. --}}
                    <x-menu-tarik-turun label="Menu">
                        <a href="{{ route('masuk') }}"
                           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-korps-50 hover:text-korps-700">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/>
                                <path fill-rule="evenodd" d="M6 10a.75.75 0 01.75-.75h9.546l-1.048-.943a.75.75 0 111.004-1.114l2.5 2.25a.75.75 0 010 1.114l-2.5 2.25a.75.75 0 11-1.004-1.114l1.048-.943H6.75A.75.75 0 016 10z" clip-rule="evenodd"/>
                            </svg>
                            Masuk sebagai Pegawai
                        </a>

                        <div class="mt-1 border-t border-slate-100 pt-2">
                            <p class="px-3 pb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Kontak</p>
                            {{-- Belum ada data kontak resmi -- tampilkan sebagai
                                 tempat kosong yang jelas, bukan info karangan. --}}
                            <dl class="space-y-1 px-3 pb-2 text-sm text-slate-500">
                                <div class="flex justify-between gap-3">
                                    <dt>Email</dt><dd>&mdash;</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>Telepon</dt><dd>&mdash;</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>Alamat</dt><dd>&mdash;</dd>
                                </div>
                            </dl>
                        </div>
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
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 text-xs text-slate-500 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="flex items-center gap-2">
                <img src="{{ asset('images/logo-kejaksaan-96.png') }}"
                     alt="" aria-hidden="true"
                     width="20" height="20"
                     class="h-5 w-auto shrink-0 object-contain opacity-70">
                {{ config('app.name') }} &mdash; Sistem Perpustakaan Digital dengan Asisten AI
            </span>
        </div>
    </footer>
    @endunless
</div>

@stack('skrip')
</body>
</html>
