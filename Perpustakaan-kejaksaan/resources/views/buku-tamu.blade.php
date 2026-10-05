@extends('layouts.publik')
@section('judul', 'Buku Tamu')
@section('tanpa-bungkus', true)
@section('tanpa-kepala', true)

@section('konten')
<div class="flex min-h-dvh flex-col items-center justify-center bg-paper-50 px-4 py-12">
    <div class="w-full max-w-lg">
        <x-tautan-kembali :href="route('gerbang')">Kembali ke pilihan awal</x-tautan-kembali>

        <div class="rounded-xl border border-korps-100 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="font-serif text-2xl font-semibold text-korps-900">Buku Tamu</h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Data ini dipakai untuk mencatat siapa saja yang mengakses layanan
                dan hanya dapat dilihat administrator.
            </p>

            <x-notifikasi />

            <form method="POST" action="{{ route('buku-tamu.simpan') }}" class="mt-6 space-y-4">
                @csrf

                @foreach ([
                    ['nama', 'Nama lengkap', 'text', 'Nama Anda'],
                    ['no_telp', 'Nomor telepon', 'tel', '0812-3456-7890'],
                    ['domisili', 'Domisili', 'text', 'Kota atau kabupaten'],
                ] as [$kolom, $label, $tipe, $contoh])
                    <div>
                        <label for="{{ $kolom }}" class="block text-sm font-medium text-slate-700">
                            {{ $label }} <span class="text-rose-600">*</span>
                        </label>
                        <input id="{{ $kolom }}" name="{{ $kolom }}" type="{{ $tipe }}" required
                               value="{{ old($kolom) }}" placeholder="{{ $contoh }}"
                               @if ($loop->first) autofocus @endif
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                        @error($kolom) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <button type="submit"
                        class="w-full rounded-lg bg-korps-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-korps-800">
                    Masuk ke Katalog
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
