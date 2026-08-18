<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &mdash; {{ config('app.name') }}</title>

    {{-- Lambang Kejaksaan sebagai ikon situs --}}
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">

<div class="min-h-full flex items-center justify-center bg-linear-to-br from-korps-900 via-korps-800 to-black px-4 py-12">
    <div class="w-full max-w-4xl">
    <div class="overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="grid md:grid-cols-2">

            {{-- Kolom kiri: identitas --}}
            <div class="flex min-w-0 flex-col items-center justify-center gap-4 px-8 py-12 text-center md:border-r md:border-slate-100">
                <img src="{{ asset('images/logo-kejaksaan.png') }}"
                     alt="Lambang Kejaksaan Republik Indonesia"
                     width="94" height="96"
                     class="h-24 w-auto object-contain">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">{{ config('app.name') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">Perpustakaan Digital dengan Asisten AI</p>
                </div>
            </div>

            {{-- Kolom kanan: formulir masuk --}}
            <div class="min-w-0 px-8 py-10 sm:px-10">
                <h2 class="text-2xl font-bold text-slate-900">Selamat Datang!</h2>
                <p class="mt-1 text-sm text-slate-500">Masuk untuk mengakses perpustakaan digital.</p>

                @if (session('sukses'))
                    <div class="mt-6 rounded-lg bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-800">
                        {{ session('sukses') }}
                    </div>
                @endif

                @error('username')
                    <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-sm text-rose-800">
                        {{ $message }}
                    </div>
                @enderror

                <form method="POST" action="{{ route('masuk') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label for="username" class="block text-sm font-medium text-slate-700">
                            Nama pengguna atau email
                        </label>
                        <div class="mt-1.5 flex rounded-lg shadow-sm ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-korps-600">
                            <span class="flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/>
                                </svg>
                            </span>
                            <input id="username" name="username" type="text" required autofocus
                                   value="{{ old('username') }}"
                                   autocomplete="username"
                                   class="block min-w-0 w-full rounded-lg border-0 bg-transparent py-2.5 pl-2.5 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">Kata sandi</label>
                        <div class="mt-1.5 flex rounded-lg shadow-sm ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-korps-600">
                            <span class="flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <input id="password" name="password" type="password" required
                                   autocomplete="current-password"
                                   class="block min-w-0 w-full rounded-lg border-0 bg-transparent py-2.5 pl-2.5 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="ingat" value="1"
                               class="rounded border-slate-300 text-korps-600 focus:ring-korps-600">
                        Ingat saya di perangkat ini
                    </label>

                    <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-korps-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-korps-800 focus:outline-none focus:ring-2 focus:ring-korps-600 focus:ring-offset-2 transition">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/>
                            <path fill-rule="evenodd" d="M6 10a.75.75 0 01.75-.75h9.546l-1.048-.943a.75.75 0 111.004-1.114l2.5 2.25a.75.75 0 010 1.114l-2.5 2.25a.75.75 0 11-1.004-1.114l1.048-.943H6.75A.75.75 0 016 10z" clip-rule="evenodd"/>
                        </svg>
                        Masuk
                    </button>
                </form>

                <p class="mt-6 text-center text-xs text-slate-400">
                    Lupa kata sandi? Hubungi administrator perpustakaan.
                </p>
            </div>
        </div>
    </div>

    <p class="mt-6 text-center text-xs text-korps-100/70">
        Akses ke sistem ini dicatat. Gunakan sesuai kewenangan Anda.
    </p>
    </div>
</div>

</body>
</html>
