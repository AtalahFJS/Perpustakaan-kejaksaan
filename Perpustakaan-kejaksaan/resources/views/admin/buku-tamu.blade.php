@extends('layouts.app')
@section('judul', 'Buku Tamu')

@section('konten')
    <x-tautan-kembali :href="route('admin.dasbor')">Kembali ke Administrasi</x-tautan-kembali>

    <h1 class="mb-1 text-2xl font-semibold text-slate-900">Buku Tamu</h1>
    <p class="mb-6 text-sm text-slate-500">
        Catatan pengunjung yang masuk katalog tanpa akun.
        <span class="font-medium text-slate-700">{{ $jumlahHariIni }}</span> kunjungan hari ini.
    </p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-semibold">Waktu</th>
                    <th class="px-4 py-3 font-semibold">Nama</th>
                    <th class="px-4 py-3 font-semibold">Nomor telepon</th>
                    <th class="px-4 py-3 font-semibold">Domisili</th>
                    <th class="px-4 py-3 font-semibold">Alamat IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tamu as $satu)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $satu->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $satu->nama }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $satu->no_telp }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $satu->domisili }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-400">{{ $satu->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-slate-500">Belum ada tamu yang mengisi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tamu->links() }}</div>
@endsection
