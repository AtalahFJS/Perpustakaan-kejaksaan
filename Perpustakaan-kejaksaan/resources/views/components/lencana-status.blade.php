{{-- F-2.9: status pemrosesan dokumen. --}}
@props(['dokumen'])

@php
    $warna = match ($dokumen->status) {
        \App\Models\Document::STATUS_READY => 'bg-emerald-100 text-emerald-800',
        \App\Models\Document::STATUS_PROCESSING => 'bg-sky-100 text-sky-800',
        \App\Models\Document::STATUS_UPLOADED => 'bg-slate-100 text-slate-700',
        \App\Models\Document::STATUS_FAILED => 'bg-rose-100 text-rose-800',
        default => 'bg-amber-100 text-amber-800',
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $warna }}">
    @if ($dokumen->status === \App\Models\Document::STATUS_PROCESSING)
        <span class="relative flex h-1.5 w-1.5">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-500 opacity-75"></span>
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-sky-600"></span>
        </span>
    @endif
    {{ $dokumen->namaStatus() }}
</span>
