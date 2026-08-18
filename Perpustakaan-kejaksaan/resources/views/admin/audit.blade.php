@extends('layouts.app')
@section('judul', 'Log Audit')

@section('konten')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Log Audit</h1>
            <p class="mt-1 text-sm text-slate-500">
                Setiap akses dokumen tercatat: siapa, dokumen apa, kapan, dan aksi apa.
            </p>
        </div>
        {{-- F-7.7 --}}
        <a href="{{ route('admin.audit.ekspor') }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Ekspor CSV
        </a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="aksi" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
            <option value="">Semua aksi</option>
            @foreach ($aksiSemua as $aksi)
                <option value="{{ $aksi }}" @selected(request('aksi') === $aksi)>{{ $aksi }}</option>
            @endforeach
        </select>
        <select name="pengguna" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
            <option value="">Semua pengguna</option>
            @foreach ($penggunaSemua as $orang)
                <option value="{{ $orang->id }}" @selected(request('pengguna') == $orang->id)>{{ $orang->name }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Terapkan
        </button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Waktu</th>
                        <th class="px-4 py-3 font-medium">Pengguna</th>
                        <th class="px-4 py-3 font-medium">Aksi</th>
                        <th class="px-4 py-3 font-medium">Objek</th>
                        <th class="px-4 py-3 font-medium">Klasifikasi</th>
                        <th class="px-4 py-3 font-medium">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($log as $entri)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-2 text-slate-500">
                                {{ $entri->created_at?->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="font-medium text-slate-800">{{ $entri->user?->name ?? 'Sistem' }}</span>
                                <span class="block text-xs text-slate-400">{{ $entri->user?->namaPeran() ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $entri->action }}</code>
                            </td>
                            <td class="px-4 py-2 text-slate-600">
                                {{ $entri->metadata['judul'] ?? ($entri->subject_type ? $entri->subject_type.' #'.$entri->subject_id : '—') }}
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-500">
                                {{ $entri->metadata['klasifikasi'] ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-400">{{ $entri->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Belum ada log.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $log->links() }}</div>
@endsection
