{{-- Tautan "kembali" yang konsisten di seluruh halaman fitur.

     Sengaja menunjuk ke halaman induk yang tetap, bukan history.back():
     tujuannya selalu dapat ditebak dan terbaca pada labelnya, sedangkan
     riwayat peramban bisa saja berisi halaman luar atau hasil kiriman
     formulir. --}}
@props(['href'])

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'mb-4 inline-flex items-center gap-1 text-sm text-slate-500 transition hover:text-korps-700']) }}>
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
    </svg>
    {{ $slot }}
</a>
