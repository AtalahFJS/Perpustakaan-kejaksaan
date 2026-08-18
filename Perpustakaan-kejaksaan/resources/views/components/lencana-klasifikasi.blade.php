{{-- F-8.5: indikator klasifikasi yang jelas di seluruh antarmuka. --}}
@props(['dokumen'])

<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $dokumen->warnaKlasifikasi() }}">
    @if ($dokumen->classification === \App\Models\Document::KLASIFIKASI_TERBATAS)
        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
        </svg>
    @endif
    {{ $dokumen->namaKlasifikasi() }}
</span>
