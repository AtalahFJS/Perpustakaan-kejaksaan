@extends('layouts.app')
@section('judul', $dokumen->title)

@section('konten')
    <x-tautan-kembali :href="route('katalog.index')">Kembali ke Katalog</x-tautan-kembali>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Kolom kiri: sampul, aksi, metadata --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="aspect-[3/4] bg-slate-100">
                    @if ($dokumen->cover_path)
                        <img src="{{ route('berkas.sampul', $dokumen) }}" alt="Sampul {{ $dokumen->title }}"
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
                    {{-- F-3.7: tombol baca dan tanya --}}
                    <a href="{{ route('pembaca.show', $dokumen) }}"
                       class="flex w-full items-center justify-center gap-2 rounded-lg bg-korps-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-korps-800 transition">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.75 16.82A7.46 7.46 0 0115.5 15c.71 0 1.4.1 2.05.28A.75.75 0 0018.5 14.5v-10a.75.75 0 00-.55-.72A9 9 0 0015.5 3.5c-1.8 0-3.47.53-4.75 1.42v11.9zM9.25 5A8.96 8.96 0 004.5 3.5c-.85 0-1.67.12-2.45.34a.75.75 0 00-.55.72v10a.75.75 0 00.95.72A7.46 7.46 0 014.5 15c1.8 0 3.47.66 4.75 1.82V5z"/>
                        </svg>
                        Baca Dokumen
                    </a>

                    @if ($dokumen->siapDitanya())
                        <a href="{{ route('chat.show', $dokumen) }}"
                           class="flex w-full items-center justify-center gap-2 rounded-lg border border-violet-300 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-800 hover:bg-violet-100 transition">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 1.5l1.6 4.3 4.4 1.6-4.4 1.6L10 13.3 8.4 9 4 7.4l4.4-1.6L10 1.5z"/>
                            </svg>
                            Tanya Dokumen Ini
                        </a>
                    @elseif (! $dokumen->bolehDiprosesAi())
                        <p class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800">
                            Dokumen ini tidak diizinkan diproses asisten AI.
                            Isinya tidak pernah dikirim ke layanan pihak ketiga.
                        </p>
                    @else
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Fitur tanya jawab belum tersedia &mdash; status dokumen:
                            <span class="font-medium">{{ $dokumen->namaStatus() }}</span>.
                        </p>
                    @endif

                    @can('download', $dokumen)
                        <a href="{{ route('berkas.unduh', $dokumen) }}"
                           class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                            Unduh berkas asli
                        </a>
                    @endcan

                    @can('update', $dokumen)
                        <a href="{{ route('dokumen.edit', $dokumen) }}"
                           class="flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-50 transition">
                            Ubah metadata
                        </a>
                    @endcan
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

        {{-- Kolom kanan: judul, abstrak, ringkasan AI --}}
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

                @can('update', $dokumen)
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                        <x-lencana-status :dokumen="$dokumen" />
                        @if ($dokumen->error_message)
                            <span class="text-xs text-rose-700">{{ Str::limit($dokumen->error_message, 120) }}</span>
                        @endif
                        @if ($dokumen->bolehDiprosesAi())
                            <form method="POST" action="{{ route('ringkasan.buat-ulang', $dokumen) }}" class="ml-auto">
                                @csrf
                                <button class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                    Buat ulang ringkasan
                                </button>
                            </form>
                        @endif
                    </div>
                @endcan
            </div>

            {{-- Modul ringkasan AI (PRD 5.5) --}}
            @if ($ringkasanSingkat || $ringkasanPoin || $ringkasanBagian->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-3">
                        <h2 class="font-semibold text-slate-900">Ringkasan Otomatis</h2>
                        <x-tanda-ai />
                    </div>

                    {{-- F-6.9 / NFR 6.5: peringatan verifikasi --}}
                    <p class="border-b border-amber-100 bg-amber-50 px-6 py-2 text-xs text-amber-800">
                        Ringkasan ini dihasilkan otomatis dan dapat keliru. Periksa kembali ke dokumen asli
                        sebelum dijadikan dasar keputusan.
                    </p>

                    <div class="divide-y divide-slate-100">
                        @if ($ringkasanSingkat)
                            <section class="panel-ai px-6 py-4">
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-900">Ringkasan singkat</h3>
                                    @can('update', $dokumen)
                                        <x-tombol-setujui :ringkasan="$ringkasanSingkat" />
                                    @endcan
                                </div>
                                <p class="text-sm leading-relaxed text-slate-700">{{ $ringkasanSingkat->content }}</p>
                            </section>
                        @endif

                        @if ($ringkasanPoin)
                            <section class="panel-ai px-6 py-4">
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-900">Poin-poin kunci</h3>
                                    @can('update', $dokumen)
                                        <x-tombol-setujui :ringkasan="$ringkasanPoin" />
                                    @endcan
                                </div>
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
                                                @can('update', $dokumen)
                                                    <div class="mt-2"><x-tombol-setujui :ringkasan="$bagian" /></div>
                                                @endcan
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </div>
            @elseif ($dokumen->bolehDiprosesAi())
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                    <p class="text-sm text-slate-600">
                        @if ($dokumen->status === \App\Models\Document::STATUS_PROCESSING)
                            Ringkasan sedang dibuat. Muat ulang halaman ini beberapa saat lagi.
                        @elseif ($dokumen->status === \App\Models\Document::STATUS_FAILED)
                            Pemrosesan dokumen gagal, sehingga ringkasan belum tersedia.
                        @else
                            Ringkasan otomatis belum tersedia untuk dokumen ini.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection
