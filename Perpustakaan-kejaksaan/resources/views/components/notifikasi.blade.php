{{-- NFR 6.5: pesan galat ditulis dalam bahasa yang dipahami pengguna non-teknis. --}}

@if (session('sukses'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('sukses') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <p class="font-medium mb-1">Ada beberapa hal yang perlu diperbaiki:</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $galat)
                    <li>{{ $galat }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
