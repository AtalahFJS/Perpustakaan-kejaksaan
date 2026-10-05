@extends('layouts.app')
@section('judul', 'Riwayat Login')

@section('konten')
    <x-tautan-kembali :href="route('admin.dasbor')">Kembali ke Administrasi</x-tautan-kembali>

    <h1 class="mb-1 text-2xl font-semibold text-slate-900">Riwayat Login</h1>
    <p class="mb-6 text-sm text-slate-500">Waktu, alamat IP, dan perangkat setiap percobaan masuk.</p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Waktu</th>
                        <th class="px-4 py-3 font-medium">Pengguna</th>
                        <th class="px-4 py-3 font-medium">Hasil</th>
                        <th class="px-4 py-3 font-medium">IP</th>
                        <th class="px-4 py-3 font-medium">Perangkat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($riwayat as $entri)
                        <tr @class(['hover:bg-slate-50', 'bg-rose-50/50' => ! $entri->berhasil])>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-500">
                                {{ $entri->created_at?->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-4 py-2 text-slate-800">
                                {{ $entri->user?->name ?? $entri->username_dicoba }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($entri->berhasil)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Berhasil</span>
                                @else
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">Gagal</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $entri->ip_address ?? '—' }}</td>
                            <td class="max-w-md truncate px-4 py-2 text-xs text-slate-400" title="{{ $entri->user_agent }}">
                                {{ $entri->user_agent ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">Belum ada riwayat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $riwayat->links() }}</div>
@endsection
