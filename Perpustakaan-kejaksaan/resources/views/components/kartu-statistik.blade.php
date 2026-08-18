@props(['label', 'nilai', 'warna' => 'slate'])

@php
    $warnaNilai = match ($warna) {
        'emerald' => 'text-emerald-700',
        'sky' => 'text-sky-700',
        'rose' => 'text-rose-700',
        default => 'text-slate-900',
    };
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
    <p class="mt-1.5 text-2xl font-semibold {{ $warnaNilai }}">{{ $nilai }}</p>
</div>
