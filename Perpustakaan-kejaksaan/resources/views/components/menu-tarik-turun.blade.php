{{-- Menu tarik-turun bilah kepala, dipakai jalur publik maupun internal.

     Kendalinya di resources/js/menu.js, bukan <details> bawaan peramban:
     di Safari, <summary> yang di-display flex tidak menutup kembali saat
     diklik ulang. Versi ini juga menutup saat diklik di luar dan saat Esc
     ditekan -- keduanya tidak diberikan <details>. --}}
@props(['label' => 'Menu'])

@php $idPanel = 'menu-panel-'.Str::random(6); @endphp

<div data-menu class="relative">
    <button type="button" data-menu-pemicu
            aria-expanded="false" aria-haspopup="true" aria-controls="{{ $idPanel }}"
            class="group flex cursor-pointer items-center gap-1.5 rounded-lg bg-white/10 px-5 py-2.5 text-base font-semibold text-white transition hover:bg-white/20">
        {{ $label }}
        <svg class="h-4 w-4 transition group-aria-expanded:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
    </button>

    <div id="{{ $idPanel }}" data-menu-panel hidden
         class="absolute right-0 z-30 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl bg-white p-2 text-slate-800 shadow-xl ring-1 ring-black/5">
        {{ $slot }}
    </div>
</div>
