@extends('layouts.app')
@section('judul', 'Kelola Kategori')

@section('konten')
    {{-- Induk halaman ini kini Kelola Dokumen, bukan Administrasi. --}}
    <x-tautan-kembali :href="route('dokumen.index')">Kembali ke Kelola Dokumen</x-tautan-kembali>

    <h1 class="mb-1 text-2xl font-semibold text-slate-900">Kelola Kategori</h1>
    <p class="mb-6 text-sm text-slate-500">
        Kategori adalah pengelompokan dokumen. Susunan di halaman ini menentukan
        urutan tampilnya pada &ldquo;Telusuri per Kategori&rdquo; di katalog.
        Siapa yang boleh melihat sebuah dokumen ditentukan saat dokumen diunggah,
        bukan oleh kategorinya.
    </p>

    {{-- Tambah kategori baru --}}
    {{-- kelas "group" wajib di <details>: atribut open ada di sini,
     dan varian group-open: menuntut .group sendiri yang [open]. --}}
    <details class="group mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        {{-- Tanda "+" hanya relevan selagi panel tertutup; saat terbuka ia
             diganti panah, sama seperti pemicu tambah-cepat di formulir
             unggah dokumen. Keduanya disetir keadaan <details open>. --}}
        <summary class="flex cursor-pointer list-none items-center gap-1.5 px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
            <svg class="h-3.5 w-3.5 shrink-0 group-open:hidden" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 4.25a.75.75 0 01.75.75v4.25H15a.75.75 0 010 1.5h-4.25V15a.75.75 0 01-1.5 0v-4.25H5a.75.75 0 010-1.5h4.25V5a.75.75 0 01.75-.75z"/>
            </svg>
            <svg class="hidden h-3.5 w-3.5 shrink-0 group-open:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
            Tambah kategori baru
        </summary>
        <form method="POST" action="{{ route('admin.kategori.simpan') }}" class="border-t border-slate-100 p-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700">Nama kategori</label>
                <input name="name" type="text" required value="{{ old('name') }}"
                       class="mt-1 block w-full max-w-md rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                <p class="mt-1 text-xs text-slate-500">Kategori baru ditempatkan di urutan paling bawah.</p>
            </div>
            <button class="mt-4 rounded-lg bg-korps-700 px-4 py-2 text-sm font-semibold text-white hover:bg-korps-800">
                Simpan kategori
            </button>
        </form>
    </details>

    <div class="space-y-3">
        @forelse ($kategoriSemua as $kategori)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid items-end gap-3 lg:grid-cols-[auto_2fr_auto_auto]">
                    {{-- Tombol urutan berada di luar <form> sunting nama agar
                         menekan salah satunya tidak ikut mengirim yang lain. --}}
                    <div class="flex items-center gap-1">
                        @foreach ([['naik', 'Naikkan', 'M10 5.5a.75.75 0 01.53.22l4.25 4.25a.75.75 0 11-1.06 1.06L10 7.31l-3.72 3.72a.75.75 0 11-1.06-1.06l4.25-4.25A.75.75 0 0110 5.5z'], ['turun', 'Turunkan', 'M10 14.5a.75.75 0 01-.53-.22l-4.25-4.25a.75.75 0 111.06-1.06L10 12.69l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-.53.22z']] as [$arah, $judul, $garis])
                            <form method="POST" action="{{ route('admin.kategori.geser', $kategori) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="arah" value="{{ $arah }}">
                                <button type="submit" title="{{ $judul }}" aria-label="{{ $judul }} {{ $kategori->name }}"
                                        @disabled($loop->parent->first && $arah === 'naik' || $loop->parent->last && $arah === 'turun')
                                        class="rounded-md border border-slate-300 p-1.5 text-slate-500 hover:bg-slate-50 hover:text-korps-700 disabled:opacity-30">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="{{ $garis }}" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </form>
                        @endforeach
                    </div>

                    {{-- Form terpisah dari tombol hapus -- keduanya tak boleh berbagi
                         <form> yang sama, sebab bidang tersembunyi @method('DELETE')
                         akan ikut terkirim walau tombol "Simpan" yang ditekan. --}}
                    <form id="ubah-kategori-{{ $kategori->id }}" method="POST"
                          action="{{ route('admin.kategori.ubah', $kategori) }}" class="contents">
                        @csrf @method('PUT')
                        <div>
                            <label class="block text-xs font-medium text-slate-500">Nama kategori</label>
                            <input name="name" type="text" required value="{{ $kategori->name }}"
                                   class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                            <p class="mt-1 text-xs text-slate-400">{{ $kategori->documents_count }} dokumen</p>
                        </div>
                        <button class="rounded-lg bg-slate-800 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-900">
                            Simpan
                        </button>
                    </form>

                    {{-- Kategori yang memuat dokumen menghapus dokumennya juga,
                         jadi konfirmasinya dua langkah: langkah kedua menyebut
                         jumlah dokumen yang ikut terhapus. Lihat
                         resources/js/konfirmasi-hapus.js. --}}
                    <form method="POST" action="{{ route('admin.kategori.hapus', $kategori) }}"
                          data-konfirmasi="Hapus kategori {{ $kategori->name }}?"
                          @if ($kategori->documents_count > 0)
                              data-konfirmasi-kedua="PERINGATAN: {{ $kategori->documents_count }} dokumen di dalam kategori ini akan ikut terhapus. Dokumen masih dapat dipulihkan dari Arsip terhapus. Lanjutkan?"
                          @endif>
                        @csrf @method('DELETE')

                        {{-- Penegasan yang diperiksa server. Tanpa ini, tidak
                             ada dokumen yang boleh ikut terhapus. --}}
                        @if ($kategori->documents_count > 0)
                            <input type="hidden" name="konfirmasi_hapus_dokumen" value="1">
                        @endif

                        <button class="rounded-lg border border-rose-300 px-4 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-50">
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                Belum ada kategori.
            </div>
        @endforelse
    </div>
@endsection
