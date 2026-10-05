@extends('layouts.app')
@section('judul', 'Tanya: '.$dokumen->title)
@section('tanpa-bungkus', true)

@section('konten')
<div class="mx-auto flex h-[calc(100vh-8rem)] max-w-4xl flex-col px-4 py-4">

    {{-- Kepala --}}
    <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
        {{-- Tautan ini hanya ikon karena kepala halaman sempit; tanpa
             aria-label pembaca layar hanya membacakan alamatnya. --}}
        <a href="{{ route('katalog.show', $dokumen) }}" title="Kembali ke detail dokumen"
           aria-label="Kembali ke detail dokumen"
           class="text-slate-400 hover:text-korps-700">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
            </svg>
        </a>
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-sm font-semibold text-slate-900">{{ $dokumen->title }}</h1>
            <p class="text-xs text-slate-500">
                Jawaban disusun hanya dari isi dokumen ini
                @if ($dokumen->page_count) &middot; {{ $dokumen->page_count }} halaman @endif
            </p>
        </div>
        <x-lencana-klasifikasi :dokumen="$dokumen" />
        <a href="{{ route('pembaca.show', $dokumen) }}"
           class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
            Buka pembaca
        </a>
        <button id="tombol-bersihkan" type="button"
                class="rounded-md px-2.5 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50">
            Bersihkan
        </button>
    </div>

    {{-- NFR 6.3: kebijakan pemakaian AI ditampilkan saat pertama kali memakai fitur chat. --}}
    <details class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-900" @if($pesan->isEmpty()) open @endif>
        <summary class="cursor-pointer font-medium">Sebelum mulai — cara kerja dan batasan asisten ini</summary>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>Jawaban disusun <strong>hanya</strong> dari potongan teks dokumen ini, lengkap dengan rujukan halaman.</li>
            <li>Bila informasi tidak ada di dokumen, asisten akan menyatakannya dan tidak menebak.</li>
            <li>Asisten <strong>tidak memberi penafsiran, opini, atau nasihat hukum</strong>.</li>
            <li>Jawaban AI dapat keliru — <strong>verifikasi selalu ke dokumen asli</strong> lewat tautan halaman.</li>
            <li>Isi dokumen berklasifikasi <em>publik</em> dan <em>internal</em> dikirim ke penyedia LLM pihak ketiga
                dengan kebijakan tanpa penyimpanan data dan tanpa pelatihan ulang. Dokumen
                <em>terbatas</em> tidak pernah dikirim.</li>
            <li>Setiap pertanyaan dicatat dalam log audit.</li>
        </ul>
    </details>

    {{-- Daftar pesan --}}
    <div id="daftar-pesan" class="flex-1 space-y-3 overflow-y-auto rounded-xl bg-slate-100 p-4">
        @forelse ($pesan as $p)
            @if ($p->role === \App\Models\ChatMessage::ROLE_USER)
                <div class="flex justify-end">
                    <div class="max-w-[80%] rounded-2xl rounded-br-sm bg-korps-700 px-4 py-2.5 text-sm text-white">
                        {{ $p->content }}
                    </div>
                </div>
            @else
                <div class="flex justify-start" data-pesan-id="{{ $p->id }}">
                    <div class="panel-ai max-w-[85%] rounded-2xl rounded-bl-sm bg-white px-4 py-3 ring-1 ring-slate-200">
                        <div class="mb-1.5"><x-tanda-ai /></div>
                        <div class="isi-ai text-sm leading-relaxed text-slate-800">
                            {!! nl2br(e($p->content)) !!}
                        </div>

                        @if (! empty($p->citations))
                            <div class="mt-2.5 flex flex-wrap items-center gap-1 border-t border-slate-100 pt-2">
                                <span class="mr-1 text-[11px] text-slate-500">Rujukan:</span>
                                @foreach ($p->citations as $sitasi)
                                    <button type="button" data-halaman="{{ $sitasi['halaman'] }}"
                                            title="{{ $sitasi['kutipan'] ?? '' }}"
                                            class="tombol-sitasi rounded-md bg-violet-100 px-1.5 py-0.5 text-[11px] font-medium text-violet-800 transition hover:bg-violet-600 hover:text-white">
                                        Hal. {{ $sitasi['halaman'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-2 flex items-center gap-1.5 text-slate-400">
                            <button type="button" data-nilai="1"
                                    @class(['tombol-umpan rounded p-1 hover:bg-slate-100 hover:text-emerald-600', 'text-korps-700 bg-slate-100' => $p->feedback === 1])
                                    title="Jawaban ini membantu">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M7.5 17.5v-8l3.2-6.4a1.6 1.6 0 012.9 1.3L12.5 8h4a1.5 1.5 0 011.47 1.8l-1.2 6A1.5 1.5 0 0115.3 17H7.5zM5.5 9.5H3a1 1 0 00-1 1v6a1 1 0 001 1h2.5v-8z"/></svg>
                            </button>
                            <button type="button" data-nilai="-1"
                                    @class(['tombol-umpan rounded p-1 hover:bg-slate-100 hover:text-rose-600', 'text-korps-700 bg-slate-100' => $p->feedback === -1])
                                    title="Jawaban ini kurang tepat">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M12.5 2.5v8l-3.2 6.4a1.6 1.6 0 01-2.9-1.3L7.5 12h-4a1.5 1.5 0 01-1.47-1.8l1.2-6A1.5 1.5 0 014.7 3h7.8zM14.5 10.5H17a1 1 0 001-1v-6a1 1 0 00-1-1h-2.5v8z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="flex h-full flex-col items-center justify-center text-center">
                <svg class="h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
                <p class="mt-3 text-sm font-medium text-slate-600">Ajukan pertanyaan tentang dokumen ini</p>
                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    @foreach (['Apa pokok isi dokumen ini?', 'Sebutkan ketentuan utamanya.', 'Siapa saja pihak yang disebut?'] as $contoh)
                        <button type="button"
                                onclick="document.getElementById('input-pertanyaan').value = this.textContent.trim(); document.getElementById('input-pertanyaan').focus();"
                                class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-600 hover:border-korps-600 hover:text-korps-700">
                            {{ $contoh }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endforelse
    </div>

    {{-- Kotak masukan --}}
    <form id="form-tanya" class="mt-3">
        <div class="flex items-end gap-2 rounded-xl border border-slate-300 bg-white p-2 shadow-sm focus-within:border-korps-600 focus-within:ring-1 focus-within:ring-korps-600">
            <textarea id="input-pertanyaan" rows="1" required minlength="3" maxlength="2000"
                      placeholder="Tulis pertanyaan Anda… (Enter untuk kirim, Shift+Enter baris baru)"
                      class="max-h-40 flex-1 resize-none border-0 bg-transparent px-2 py-1.5 text-sm placeholder:text-slate-400 focus:ring-0"></textarea>
            <button id="tombol-kirim" type="submit"
                    class="shrink-0 rounded-lg bg-korps-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-korps-800 disabled:cursor-not-allowed disabled:opacity-50">
                Kirim
            </button>
        </div>
        <p class="mt-1.5 px-1 text-[11px] text-slate-500">
            {{-- F-6.9 --}}
            Jawaban dihasilkan AI dan perlu diverifikasi ke dokumen asli.
            {{-- F-7.6 --}}
            Sisa kuota hari ini: <span id="sisa-kuota" class="font-medium">{{ $sisaKuota }}</span> pertanyaan.
        </p>
    </form>
</div>
@endsection

@push('skrip')
<script type="module">
    window.PustakaAI.pasangChat({
        urlTanya: @json(route('chat.tanya', $dokumen)),
        urlBersihkan: @json(route('chat.bersihkan', $dokumen)),
        urlPembaca: @json(route('pembaca.show', $dokumen)),
        urlUmpanBalik: @json(route('chat.umpan-balik', ['message' => '__ID__'])),
        csrf: @json(csrf_token()),
    });
</script>
@endpush
