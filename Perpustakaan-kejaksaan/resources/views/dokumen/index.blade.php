@extends('layouts.app')
@section('judul', 'Kelola Dokumen')

@section('konten')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Kelola Dokumen</h1>
            <p class="mt-1 text-sm text-slate-500">Unggah, sunting metadata, dan pantau status pemrosesan.</p>
        </div>
        <a href="{{ route('dokumen.create') }}"
           class="rounded-lg bg-korps-700 px-4 py-2 text-sm font-semibold text-white hover:bg-korps-800 transition">
            + Unggah Dokumen
        </a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari judul…"
               class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
        <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
            <option value="">Semua status</option>
            @foreach (\App\Models\Document::SEMUA_STATUS as $kode => $label)
                <option value="{{ $kode }}" @selected(request('status') === $kode)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-600">
            <input type="checkbox" name="terhapus" value="1" @checked(request('terhapus'))
                   class="rounded border-slate-300 text-korps-600 focus:ring-korps-600">
            Arsip terhapus
        </label>
        <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Terapkan
        </button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Judul</th>
                        <th class="px-4 py-3 font-medium">Klasifikasi</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Diunggah</th>
                        <th class="px-4 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($dokumen as $item)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('katalog.show', $item) }}" class="font-medium text-slate-900 hover:text-korps-700">
                                    {{ $item->title }}
                                </a>
                                <div class="text-xs text-slate-500">
                                    {{ $item->author ?: '—' }}
                                    @if ($item->category) &middot; {{ $item->category->name }} @endif
                                    &middot; {{ $item->ukuranTerbaca() }}
                                </div>
                                @if ($item->status === \App\Models\Document::STATUS_FAILED && $item->error_message)
                                    <div class="mt-1 rounded bg-rose-50 px-2 py-1 text-xs text-rose-700">
                                        {{ Str::limit($item->error_message, 160) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-lencana-klasifikasi :dokumen="$item" /></td>
                            <td class="px-4 py-3"><x-lencana-status :dokumen="$item" /></td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $item->created_at?->format('d/m/Y H:i') }}<br>
                                <span class="text-slate-400">{{ $item->uploader?->name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    @if ($item->trashed())
                                        <form method="POST" action="{{ route('dokumen.restore', $item->id) }}">
                                            @csrf
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
                                                Pulihkan
                                            </button>
                                        </form>
                                        @if (auth()->user()->isAdmin())
                                            <form method="POST" action="{{ route('admin.dokumen.force-destroy', $item->id) }}"
                                                  onsubmit="return confirm('Hapus permanen? Tindakan ini tidak dapat dibatalkan.')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">
                                                    Hapus permanen
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        @if ($item->status === \App\Models\Document::STATUS_FAILED && $item->bolehDiprosesAi())
                                            {{-- F-2.10 --}}
                                            <form method="POST" action="{{ route('dokumen.ingest-ulang', $item) }}">
                                                @csrf
                                                <button class="rounded-md px-2 py-1 text-xs font-medium text-sky-700 hover:bg-sky-50">
                                                    Proses ulang
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('dokumen.edit', $item) }}"
                                           class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100">
                                            Ubah
                                        </a>
                                        <form method="POST" action="{{ route('dokumen.destroy', $item) }}"
                                              onsubmit="return confirm('Pindahkan dokumen ini ke arsip terhapus?')">
                                            @csrf @method('DELETE')
                                            <button class="rounded-md px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-slate-500">
                                Belum ada dokumen yang cocok.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $dokumen->links() }}</div>
@endsection
