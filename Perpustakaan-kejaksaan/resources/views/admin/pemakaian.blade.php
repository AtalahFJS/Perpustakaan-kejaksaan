@extends('layouts.app')
@section('judul', 'Pemakaian AI')

@section('konten')
    <h1 class="mb-1 text-2xl font-semibold text-slate-900">Laporan Pemakaian AI</h1>
    <p class="mb-6 text-sm text-slate-500">
        Pemakaian token dan estimasi biaya per periode. Biaya dihitung dari tarif model yang tercatat saat pemanggilan.
    </p>

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="block text-xs font-medium text-slate-500">Dari</label>
            <input type="date" name="dari" value="{{ $dari->format('Y-m-d') }}"
                   class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Sampai</label>
            <input type="date" name="sampai" value="{{ $sampai->format('Y-m-d') }}"
                   class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
        </div>
        <button class="rounded-lg bg-korps-700 px-4 py-2 text-sm font-semibold text-white hover:bg-korps-800">
            Tampilkan
        </button>
    </form>

    {{-- F-7.4: ringkasan per jenis operasi --}}
    <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Ringkasan per operasi</h2>
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-2.5 font-medium">Operasi</th>
                    <th class="px-5 py-2.5 font-medium text-right">Pemanggilan</th>
                    <th class="px-5 py-2.5 font-medium text-right">Token masuk</th>
                    <th class="px-5 py-2.5 font-medium text-right">Token keluar</th>
                    <th class="px-5 py-2.5 font-medium text-right">Latensi rata-rata</th>
                    <th class="px-5 py-2.5 font-medium text-right">Estimasi biaya</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($ringkas as $baris)
                    <tr>
                        <td class="px-5 py-2.5 font-medium text-slate-800">{{ ucfirst($baris->operation) }}</td>
                        <td class="px-5 py-2.5 text-right">{{ number_format($baris->panggilan) }}</td>
                        <td class="px-5 py-2.5 text-right">{{ number_format($baris->tokens_in) }}</td>
                        <td class="px-5 py-2.5 text-right">{{ number_format($baris->tokens_out) }}</td>
                        <td class="px-5 py-2.5 text-right">{{ number_format($baris->latensi_rata) }} ms</td>
                        <td class="px-5 py-2.5 text-right font-semibold">${{ number_format((float) $baris->biaya, 4) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Belum ada pemakaian pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Pemakaian tertinggi per pengguna, untuk mengawasi kuota (F-7.6) --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Pemakaian per pengguna</h2>
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($perPengguna as $baris)
                        <tr>
                            <td class="px-5 py-2 text-slate-800">{{ $baris->user?->name ?? 'Sistem' }}</td>
                            <td class="px-5 py-2 text-right text-slate-500">{{ number_format($baris->panggilan) }}×</td>
                            <td class="px-5 py-2 text-right font-medium">${{ number_format((float) $baris->biaya, 4) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-slate-500">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- F-7.3: log rinci setiap pemanggilan LLM --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-900">Log pemanggilan</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Waktu</th>
                            <th class="px-4 py-2 font-medium">Pengguna</th>
                            <th class="px-4 py-2 font-medium">Operasi</th>
                            <th class="px-4 py-2 font-medium">Model</th>
                            <th class="px-4 py-2 font-medium text-right">Token</th>
                            <th class="px-4 py-2 font-medium text-right">Biaya</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($log as $entri)
                            <tr @class(['bg-rose-50' => ! $entri->berhasil])>
                                <td class="whitespace-nowrap px-4 py-2 text-slate-500">{{ $entri->created_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-slate-700">{{ $entri->user?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $entri->operation }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $entri->model }}</td>
                                <td class="px-4 py-2 text-right text-xs text-slate-500">
                                    {{ number_format($entri->tokens_in) }} / {{ number_format($entri->tokens_out) }}
                                </td>
                                <td class="px-4 py-2 text-right">${{ number_format((float) $entri->cost_estimate, 5) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada log.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $log->links() }}</div>
        </div>
    </div>
@endsection
