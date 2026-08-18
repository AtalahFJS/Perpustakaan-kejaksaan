{{-- F-5.5: pustakawan menyetujui atau menyembunyikan ringkasan. --}}
@props(['ringkasan'])

<form method="POST" action="{{ route('ringkasan.setujui', $ringkasan) }}" class="shrink-0">
    @csrf
    <button type="submit"
            @class([
                'rounded-md px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset transition',
                'bg-emerald-50 text-emerald-700 ring-emerald-600/20 hover:bg-emerald-100' => $ringkasan->is_approved,
                'bg-amber-50 text-amber-800 ring-amber-600/20 hover:bg-amber-100' => ! $ringkasan->is_approved,
            ])>
        {{ $ringkasan->is_approved ? 'Disetujui — klik untuk sembunyikan' : 'Belum disetujui — klik untuk setujui' }}
    </button>
</form>
