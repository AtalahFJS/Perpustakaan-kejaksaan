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
<body class="h-full bg-slate-50 text-slate-800 antialiased">

<div class="min-h-full flex flex-col">
    <header class="bg-korps-800 text-white shadow-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between gap-4">
                <a href="{{ route('katalog.index') }}" class="flex items-center gap-3 shrink-0">
                    {{-- Sumber dirender pada ~3x ukuran tampil (120px untuk
                         tinggi tampil 40px) supaya detail halus lambang --
                         tiga lidah api, untaian padi -- tetap tajam di layar
                         Retina, bukan buram karena raster kecil diperbesar.
                         width/height eksplisit menjaga ukuran sebelum CSS
                         termuat, agar tata letak tidak bergeser. --}}
                    <img src="{{ asset('images/logo-kejaksaan-header.png') }}"
                         alt="Lambang Kejaksaan Republik Indonesia"
                         width="39" height="40"
                         class="h-10 w-auto shrink-0 object-contain">
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <nav class="hidden md:flex items-center gap-1 text-sm">
                    @php $r = request()->route()?->getName() ?? ''; @endphp
                    <x-nav-tautan :href="route('katalog.index')" :aktif="str_starts_with($r, 'katalog')">Katalog</x-nav-tautan>

                    @if (auth()->user()->isPustakawan())
                        <x-nav-tautan :href="route('dokumen.index')" :aktif="str_starts_with($r, 'dokumen')">Kelola Dokumen</x-nav-tautan>
                    @endif

                    @if (auth()->user()->isAdmin())
                        <x-nav-tautan :href="route('admin.dasbor')" :aktif="str_starts_with($r, 'admin')">Administrasi</x-nav-tautan>
                    @endif
                </nav>

                <div class="flex items-center gap-3">
                    <div class="hidden sm:block text-right leading-tight">
                        <div class="text-sm font-medium">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-korps-100">{{ auth()->user()->namaPeran() }}</div>
                    </div>
                    <form method="POST" action="{{ route('keluar') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-md bg-white/10 px-3 py-1.5 text-sm font-medium hover:bg-white/20 transition">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>

            {{-- Navigasi ringkas untuk layar kecil --}}
            <nav class="md:hidden flex gap-1 pb-2 text-sm overflow-x-auto">
                <x-nav-tautan :href="route('katalog.index')" :aktif="str_starts_with($r ?? '', 'katalog')">Katalog</x-nav-tautan>
                @if (auth()->user()->isPustakawan())
                    <x-nav-tautan :href="route('dokumen.index')" :aktif="str_starts_with($r ?? '', 'dokumen')">Kelola</x-nav-tautan>
                @endif
                @if (auth()->user()->isAdmin())
                    <x-nav-tautan :href="route('admin.dasbor')" :aktif="str_starts_with($r ?? '', 'admin')">Admin</x-nav-tautan>
                @endif
            </nav>
        </div>
    </header>

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

    <footer class="border-t border-slate-200 bg-white">
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
</div>

@stack('skrip')
</body>
</html>
