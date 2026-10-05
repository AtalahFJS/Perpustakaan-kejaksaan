@extends('layouts.app')
@section('judul', 'Dasbor Administrasi')

@section('konten')
    <x-tautan-kembali :href="route('katalog.index')">Kembali ke Katalog</x-tautan-kembali>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">Dasbor Administrasi</h1>
        <nav class="flex flex-wrap gap-2 text-sm">
            {{-- Kategori dipindah ke Kelola Dokumen: mengelola kategori
                 adalah bagian dari menata koleksi, bukan administrasi sistem
                 seperti pengguna, audit, dan pemakaian AI. --}}
            <a href="{{ route('admin.pengguna') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">Pengguna</a>
            <a href="{{ route('admin.audit') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">Log Audit</a>
            <a href="{{ route('admin.pemakaian') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">Pemakaian AI</a>
            <a href="{{ route('admin.buku-tamu') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">Buku Tamu</a>
            <a href="{{ route('admin.riwayat-login') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">Riwayat Login</a>
        </nav>
    </div>

    {{-- NFR 6.4: status layanan AI, agar mode degradasi terlihat jelas. --}}
    <div @class([
        'mb-6 flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm',
        'bg-emerald-50 text-emerald-800' => $layananAiSehat,
        'bg-amber-50 text-amber-900' => ! $layananAiSehat,
    ])>
        <span @class([
            'h-2 w-2 rounded-full',
            'bg-emerald-500' => $layananAiSehat,
            'bg-amber-500' => ! $layananAiSehat,
        ])></span>
        @if ($layananAiSehat)
            Layanan AI aktif dan dapat dihubungi.
        @else
            Layanan AI tidak dapat dihubungi. Katalog, pencarian, pembaca, dan ringkasan tersimpan tetap berjalan normal.
        @endif
    </div>

    {{-- F-7.1: jumlah dokumen, status pemrosesan, pengguna aktif --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kartu-statistik label="Total dokumen" :nilai="number_format($totalDokumen)" />
        <x-kartu-statistik label="Siap ditanya" :nilai="number_format($perStatus[\App\Models\Document::STATUS_READY] ?? 0)" warna="emerald" />
        <x-kartu-statistik label="Sedang diproses" :nilai="number_format($perStatus[\App\Models\Document::STATUS_PROCESSING] ?? 0)" warna="sky" />
        <x-kartu-statistik label="Gagal diproses" :nilai="number_format($perStatus[\App\Models\Document::STATUS_FAILED] ?? 0)" warna="rose" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Dokumen per klasifikasi</h2>
            <dl class="space-y-2 text-sm">
                @foreach (\App\Models\Document::SEMUA_KLASIFIKASI as $kode => $label)
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600">{{ $label }}</dt>
                        <dd class="font-semibold text-slate-900">{{ number_format($perKlasifikasi[$kode] ?? 0) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Pengguna</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Total akun</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($totalPengguna) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Akun aktif</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($penggunaAktif) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Login 7 hari terakhir</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($penggunaLogin7Hari) }}</dd>
                </div>
            </dl>
        </div>

        {{-- F-7.3 / F-7.4: pemakaian token dan estimasi biaya --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Pemakaian AI (30 hari)</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Pemanggilan</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($pemakaian->panggilan ?? 0) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Token masuk</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($pemakaian->tokens_in ?? 0) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Token keluar</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($pemakaian->tokens_out ?? 0) }}</dd>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-2">
                    <dt class="text-slate-600">Estimasi biaya</dt>
                    <dd class="font-semibold text-slate-900">${{ number_format((float) ($pemakaian->biaya ?? 0), 4) }}</dd>
                </div>
            </dl>
            <a href="{{ route('admin.pemakaian') }}" class="mt-3 inline-block text-xs font-medium text-korps-700 hover:underline">
                Lihat laporan lengkap →
            </a>
        </div>
    </div>

    {{-- F-7.2: aktivitas audit terbaru --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Aktivitas terbaru</h2>
            <a href="{{ route('admin.audit') }}" class="text-xs font-medium text-korps-700 hover:underline">Semua log →</a>
        </div>
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <tbody class="divide-y divide-slate-100">
                @forelse ($auditTerbaru as $entri)
                    <tr>
                        <td class="px-5 py-2 text-slate-500 whitespace-nowrap">{{ $entri->created_at?->format('d/m H:i') }}</td>
                        <td class="px-5 py-2 font-medium text-slate-800">{{ $entri->user?->name ?? 'Sistem' }}</td>
                        <td class="px-5 py-2 text-slate-600">{{ $entri->action }}</td>
                        <td class="px-5 py-2 text-slate-500">{{ $entri->metadata['judul'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-8 text-center text-slate-500">Belum ada aktivitas tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
