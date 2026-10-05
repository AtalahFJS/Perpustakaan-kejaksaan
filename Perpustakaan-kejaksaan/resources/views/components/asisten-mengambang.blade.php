{{-- Asisten dokumen mengambang (pembaruan 26 Agustus 2026).

     Dulu berupa halaman tersendiri; kini menempel di pojok kanan bawah agar
     dapat dipanggil dari mana saja tanpa meninggalkan katalog yang sedang
     ditelusuri. Kendalinya di resources/js/asisten.js.

     Hanya untuk pengguna yang sudah masuk -- lihat AsistenController. --}}
<div id="asisten-mengambang" data-url="{{ route('asisten.tanya') }}"
     class="fixed bottom-5 right-5 z-40 flex flex-col items-end gap-3 print:hidden">

    <div id="asisten-panel" hidden
         class="flex h-[28rem] w-[22rem] max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-xl border border-korps-100 bg-white shadow-2xl">

        <div class="flex items-start justify-between gap-2 border-b border-paper-200 bg-paper-50 px-4 py-3">
            <div>
                <p class="font-serif text-sm font-semibold text-korps-900">Asisten Dokumen</p>
                <p class="text-xs text-slate-500">Menjawab dari koleksi yang boleh Anda akses.</p>
            </div>
            <button type="button" id="asisten-tutup" aria-label="Tutup asisten"
                    class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 10-1.06-1.06L10 8.94 6.28 5.22z"/>
                </svg>
            </button>
        </div>

        <div id="asisten-percakapan" class="flex-1 space-y-3 overflow-y-auto px-4 py-3">
            <div class="rounded-lg bg-paper-50 px-3 py-2 text-sm leading-relaxed text-slate-600">
                Sebutkan yang Anda cari, misalnya
                <span class="font-medium text-slate-800">&ldquo;peraturan tentang pengadaan barang&rdquo;</span>.
                Saya tunjukkan dokumen yang paling sesuai beserta halamannya.
            </div>
        </div>

        <form id="asisten-form" class="border-t border-paper-200 p-3">
            <div class="flex items-end gap-2">
                <label for="asisten-pertanyaan" class="sr-only">Pertanyaan</label>
                <input id="asisten-pertanyaan" type="text" maxlength="2000" required
                       placeholder="Tanyakan dokumen yang dicari…"
                       class="w-full rounded-md border-slate-300 py-2 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                <button type="submit" id="asisten-kirim" aria-label="Kirim pertanyaan"
                        class="shrink-0 rounded-md bg-korps-700 p-2 text-white transition hover:bg-korps-800 disabled:opacity-60">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3.4 2.6a.75.75 0 00-1.02.94l1.9 5.21 8.2 1.25-8.2 1.25-1.9 5.21a.75.75 0 001.02.94l14-7a.75.75 0 000-1.34l-14-7z"/>
                    </svg>
                </button>
            </div>
            <p class="mt-2 text-[11px] leading-snug text-slate-400">
                Jawaban AI perlu diverifikasi ke dokumen asli.
            </p>
        </form>
    </div>

    <button type="button" id="asisten-pemicu" aria-expanded="false" aria-controls="asisten-panel"
            class="flex items-center gap-2 rounded-full bg-korps-700 px-5 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-korps-800">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 1.5l1.6 4.3 4.4 1.6-4.4 1.6L10 13.3 8.4 9 4 7.4l4.4-1.6L10 1.5z"/>
        </svg>
        Tanya Asisten
    </button>
</div>
