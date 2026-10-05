@extends('layouts.publik')
@section('judul', $dokumen->title)

@section('konten')
    <x-tautan-kembali :href="route('publik.katalog.index')">Kembali ke Katalog</x-tautan-kembali>

    <div class="grid gap-6 lg:grid-cols-3">

        <div class="lg:col-span-1 space-y-4">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="aspect-[3/4] bg-slate-100">
                    @if ($dokumen->cover_path)
                        <img src="{{ route('publik.berkas.sampul', $dokumen) }}" alt="Sampul {{ $dokumen->title }}"
                             class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center text-slate-300">
                            <svg class="h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                        </div>
                    @endif
                </div>

                <div class="space-y-2 p-4">
                    <a href="{{ route('publik.pembaca.show', $dokumen) }}"
                       class="flex w-full items-center justify-center gap-2 rounded-lg bg-korps-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-korps-800 transition">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.75 16.82A7.46 7.46 0 0115.5 15c.71 0 1.4.1 2.05.28A.75.75 0 0018.5 14.5v-10a.75.75 0 00-.55-.72A9 9 0 0015.5 3.5c-1.8 0-3.47.53-4.75 1.42v11.9zM9.25 5A8.96 8.96 0 004.5 3.5c-.85 0-1.67.12-2.45.34a.75.75 0 00-.55.72v10a.75.75 0 00.95.72A7.46 7.46 0 014.5 15c1.8 0 3.47.66 4.75 1.82V5z"/>
                        </svg>
                        Baca Dokumen
                    </a>

                    <p class="rounded-lg bg-korps-50 px-3 py-2 text-xs text-korps-800">
                        <a href="{{ route('masuk') }}" class="font-medium underline">Masuk</a> untuk mengunduh berkas asli
                        dan memakai asisten tanya-jawab AI.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Metadata</h2>
                <dl class="space-y-2 text-sm">
                    @foreach ([
                        'Penulis' => $dokumen->author,
                        'Penerbit' => $dokumen->publisher,
                        'Tahun' => $dokumen->year,
                        'Kategori' => $dokumen->category?->name,
                        'Nomor dokumen' => $dokumen->document_number,
                        'ISBN' => $dokumen->isbn,
                        'Jumlah halaman' => $dokumen->page_count,
                        'Ukuran berkas' => $dokumen->ukuranTerbaca(),
                    ] as $label => $nilai)
                        @if (filled($nilai))
                            <div class="flex justify-between gap-3">
                                <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                                <dd class="text-right font-medium text-slate-800">{{ $nilai }}</dd>
                            </div>
                        @endif
                    @endforeach
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-slate-500">Klasifikasi</dt>
                        <dd><x-lencana-klasifikasi :dokumen="$dokumen" /></dd>
                    </div>
                </dl>

                @if ($dokumen->tags->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3">
                        @foreach ($dokumen->tags as $tag)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ $dokumen->title }}</h1>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $dokumen->author ?: 'Penulis tidak dicantumkan' }}
                    @if ($dokumen->publisher) &middot; {{ $dokumen->publisher }} @endif
                    @if ($dokumen->year) &middot; {{ $dokumen->year }} @endif
                </p>

                @if ($dokumen->abstract)
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <h2 class="mb-1.5 text-sm font-semibold text-slate-900">Abstrak</h2>
                        <p class="text-sm leading-relaxed text-slate-700">{{ $dokumen->abstract }}</p>
                    </div>
                @endif
            </div>

            @if ($ringkasanSingkat || $ringkasanPoin || $ringkasanBagian->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-3">
                        <h2 class="font-semibold text-slate-900">Ringkasan Otomatis</h2>
                        <x-tanda-ai />
                    </div>

                    <p class="border-b border-amber-100 bg-amber-50 px-6 py-2 text-xs text-amber-800">
                        Ringkasan ini dihasilkan otomatis dan dapat keliru. Periksa kembali ke dokumen asli
                        sebelum dijadikan dasar keputusan.
                    </p>

                    <div class="divide-y divide-slate-100">
                        @if ($ringkasanSingkat)
                            <section class="panel-ai px-6 py-4">
                                <h3 class="mb-1.5 text-sm font-semibold text-slate-900">Ringkasan singkat</h3>
                                <p class="text-sm leading-relaxed text-slate-700">{{ $ringkasanSingkat->content }}</p>
                            </section>
                        @endif

                        @if ($ringkasanPoin)
                            <section class="panel-ai px-6 py-4">
                                <h3 class="mb-1.5 text-sm font-semibold text-slate-900">Poin-poin kunci</h3>
                                <ul class="list-disc space-y-1 pl-5 text-sm leading-relaxed text-slate-700">
                                    @foreach (preg_split('/\R+/', trim($ringkasanPoin->content)) as $poin)
                                        @php $poin = ltrim($poin, "-*• \t"); @endphp
                                        @if ($poin !== '')
                                            <li>{{ $poin }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            </section>
                        @endif

                        @if ($ringkasanBagian->isNotEmpty())
                            <section class="px-6 py-4">
                                <h3 class="mb-2 text-sm font-semibold text-slate-900">Ringkasan per bagian</h3>
                                <div class="space-y-2">
                                    @foreach ($ringkasanBagian as $bagian)
                                        <details class="group rounded-lg border border-slate-200">
                                            <summary class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                                {{ $bagian->scope_label ?: 'Bagian '.($loop->iteration) }}
                                                <svg class="h-4 w-4 text-slate-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                                </svg>
                                            </summary>
                                            <div class="panel-ai border-t border-slate-100 px-3 py-2.5 text-sm leading-relaxed text-slate-700">
                                                {{ $bagian->content }}
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
