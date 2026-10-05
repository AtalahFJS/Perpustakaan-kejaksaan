import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { cpSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const akar = dirname(fileURLToPath(import.meta.url));

/**
 * Sejak PDF.js v6, dekoder gambar (JBIG2 & JPEG 2000) tidak lagi ikut di
 * dalam berkas JS, melainkan berupa modul WebAssembly terpisah yang diambil
 * saat dibutuhkan lewat parameter `wasmUrl`. Tanpa berkas ini, dokumen hasil
 * pindaian -- yang gambarnya berkode JBIG2 -- gagal di-decode dan halamannya
 * tampil putih kosong tanpa galat yang mencolok.
 *
 * Berkasnya disalin ke public/pdfjs-wasm/ supaya dapat disajikan langsung,
 * baik saat `npm run dev` maupun `npm run build`.
 */
function salinWasmPdfjs() {
    const salin = () => {
        const tujuan = resolve(akar, 'public/pdfjs-wasm');
        mkdirSync(tujuan, { recursive: true });
        cpSync(resolve(akar, 'node_modules/pdfjs-dist/wasm'), tujuan, { recursive: true });
    };

    return {
        name: 'salin-wasm-pdfjs',
        buildStart: salin,
        configureServer: salin,
    };
}

export default defineConfig({
    plugins: [
        salinWasmPdfjs(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // Wajah serif untuk judul & kop surat halaman publik -- lihat
                // catatan token di resources/css/app.css (--font-serif).
                bunny('Newsreader', {
                    weights: [500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
