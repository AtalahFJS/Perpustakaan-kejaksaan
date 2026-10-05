{{-- "Telah dilihat" pada kartu katalog -- dihitung dari log audit F-7.2
     (Document::viewLogs), bukan angka terpisah. --}}
@props(['dokumen'])

@php
    $jumlah = $dokumen->dilihat_count ?? 0;
    $terbaca = $jumlah >= 1000 ? number_format($jumlah / 1000, 1).'Rb' : number_format($jumlah);
@endphp

<span class="inline-flex items-center gap-1 text-slate-500" title="{{ number_format($jumlah) }} kali dilihat">
    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
        <path d="M10 3.5c-4.1 0-7.4 2.6-8.8 6 1.4 3.4 4.7 6 8.8 6s7.4-2.6 8.8-6c-1.4-3.4-4.7-6-8.8-6zM10 13a3.5 3.5 0 110-7 3.5 3.5 0 010 7z"/>
    </svg>
    {{ $terbaca }} Dilihat
</span>
