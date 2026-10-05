/**
 * Pembaca dokumen di peramban (PRD 5.4).
 *
 * Memakai PDF.js sehingga tampilan dan perilaku konsisten di semua
 * peramban, dan agar melompat ke halaman tertentu (F-4.4) benar-benar
 * dapat diandalkan saat pengguna mengeklik sitasi jawaban AI.
 *
 * Berkas diambil lewat rute terautentikasi, bukan URL langsung (F-4.3).
 */
import * as pdfjsLib from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;

export function pasangPembaca(opsi) {
    const { urlBerkas, urlPenanda, halamanAwal, csrf, urlWasm } = opsi;

    // PDF.js menyambung nama berkas langsung ke belakang nilai ini
    // (mis. "jbig2.wasm"), jadi garis miring penutup wajib ada.
    const wasmUrl = urlWasm ? (urlWasm.endsWith('/') ? urlWasm : `${urlWasm}/`) : undefined;

    const wadah = document.getElementById('wadah-halaman');
    const statusEl = document.getElementById('status-pembaca');
    const inputHalaman = document.getElementById('input-halaman');
    const totalEl = document.getElementById('total-halaman');
    const labelZoom = document.getElementById('label-zoom');
    const inputCari = document.getElementById('input-cari');
    const hasilCari = document.getElementById('hasil-cari');

    let pdf = null;
    let skala = 1.3;
    let halamanSekarang = halamanAwal || 1;
    let sedangGambar = false;
    const halamanTergambar = new Map();

    function setStatus(teks) {
        statusEl.textContent = teks;
        statusEl.classList.toggle('hidden', teks === '');
    }

    async function muat() {
        setStatus('Memuat dokumen…');
        try {
            pdf = await pdfjsLib.getDocument({
                url: urlBerkas,
                withCredentials: true,
                // Tanpa ini, dokumen hasil pindaian (gambar JBIG2/JPEG 2000)
                // gagal di-decode dan halamannya tampil putih kosong.
                wasmUrl,
            }).promise;
        } catch (galat) {
            // NFR 6.5: pesan galat dalam bahasa yang dipahami pengguna.
            setStatus('Dokumen tidak dapat dimuat. Coba muat ulang halaman, atau hubungi pustakawan bila berlanjut.');
            console.error(galat);
            return;
        }

        totalEl.textContent = pdf.numPages;
        inputHalaman.max = pdf.numPages;

        // Siapkan wadah kosong untuk setiap halaman supaya penggulungan
        // punya tinggi yang benar sejak awal.
        for (let n = 1; n <= pdf.numPages; n++) {
            const kotak = document.createElement('div');
            kotak.className = 'relative mx-auto mb-4 bg-white shadow-sm ring-1 ring-slate-200';
            kotak.dataset.halaman = String(n);
            kotak.style.minHeight = '600px';
            wadah.appendChild(kotak);
        }

        setStatus('');
        amatiHalaman();
        await gambarSekitar(halamanSekarang);
        lompatKe(halamanSekarang, false);
    }

    /** Menggambar satu halaman ke canvas bila belum digambar. */
    async function gambarHalaman(n) {
        if (halamanTergambar.get(n) === skala) return;

        const kotak = wadah.querySelector(`[data-halaman="${n}"]`);
        if (!kotak) return;

        const halaman = await pdf.getPage(n);
        const viewport = halaman.getViewport({ scale: skala });
        const rasio = window.devicePixelRatio || 1;

        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width * rasio);
        canvas.height = Math.floor(viewport.height * rasio);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;
        canvas.className = 'block';

        // Skala piksel perangkat diterapkan lewat parameter `transform`,
        // cara yang dianjurkan PDF.js agar halaman tetap tajam di layar Retina.
        await halaman.render({
            canvas,
            viewport,
            transform: rasio === 1 ? null : [rasio, 0, 0, rasio, 0, 0],
        }).promise;

        kotak.replaceChildren(canvas, labelHalaman(n));
        kotak.style.minHeight = '';
        kotak.style.width = `${Math.floor(viewport.width)}px`;
        halamanTergambar.set(n, skala);
    }

    function labelHalaman(n) {
        const label = document.createElement('div');
        label.className =
            'absolute bottom-1 right-2 rounded bg-slate-900/60 px-1.5 py-0.5 text-[10px] text-white';
        label.textContent = `Hal. ${n}`;
        return label;
    }

    async function gambarSekitar(n) {
        if (sedangGambar) return;
        sedangGambar = true;
        for (let i = Math.max(1, n - 1); i <= Math.min(pdf.numPages, n + 2); i++) {
            await gambarHalaman(i);
        }
        sedangGambar = false;
    }

    /** Menggambar halaman hanya ketika mendekati layar (hemat memori). */
    function amatiHalaman() {
        const pengamat = new IntersectionObserver(
            (entri) => {
                entri.forEach((e) => {
                    const n = Number(e.target.dataset.halaman);
                    if (e.isIntersecting) {
                        gambarHalaman(n);
                        if (e.intersectionRatio > 0.5) {
                            halamanSekarang = n;
                            inputHalaman.value = n;
                            simpanPenanda(n);
                        }
                    }
                });
            },
            { root: wadah.parentElement, rootMargin: '200px 0px', threshold: [0, 0.5] },
        );

        wadah.querySelectorAll('[data-halaman]').forEach((el) => pengamat.observe(el));
    }

    /** F-4.4: melompat ke halaman tertentu, dipakai juga oleh tautan sitasi. */
    async function lompatKe(n, halus = true) {
        n = Math.min(Math.max(1, Number(n) || 1), pdf.numPages);
        halamanSekarang = n;
        inputHalaman.value = n;

        await gambarSekitar(n);

        const kotak = wadah.querySelector(`[data-halaman="${n}"]`);
        kotak?.scrollIntoView({ behavior: halus ? 'smooth' : 'auto', block: 'start' });

        // Sorot sejenak supaya pengguna tahu ia mendarat di halaman yang benar.
        if (kotak) {
            kotak.classList.add('ring-2', 'ring-violet-500');
            setTimeout(() => kotak.classList.remove('ring-2', 'ring-violet-500'), 1600);
        }

        simpanPenanda(n);
    }

    /** F-4.5: penanda halaman terakhir dibaca per pengguna (tidak tersedia di pembaca publik). */
    let jedaSimpan;
    function simpanPenanda(n) {
        if (!urlPenanda) return;

        clearTimeout(jedaSimpan);
        jedaSimpan = setTimeout(() => {
            fetch(urlPenanda, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ halaman: n }),
            }).catch(() => {
                /* penanda bersifat opsional -- kegagalan diabaikan */
            });
        }, 800);
    }

    async function ubahZoom(delta) {
        skala = Math.min(3, Math.max(0.5, skala + delta));
        labelZoom.textContent = `${Math.round(skala * 100)}%`;
        halamanTergambar.clear();
        await gambarSekitar(halamanSekarang);
        lompatKe(halamanSekarang, false);
    }

    /** F-4.2: pencarian teks di dalam pembaca. */
    async function cariTeks(kata) {
        if (!kata || kata.length < 2) {
            hasilCari.textContent = '';
            return;
        }

        hasilCari.textContent = 'Mencari…';
        const cocok = [];
        const pola = kata.toLowerCase();

        for (let n = 1; n <= pdf.numPages; n++) {
            const halaman = await pdf.getPage(n);
            const isi = await halaman.getTextContent();
            const teks = isi.items.map((i) => i.str).join(' ').toLowerCase();
            if (teks.includes(pola)) cocok.push(n);
            if (cocok.length >= 50) break;
        }

        if (cocok.length === 0) {
            hasilCari.textContent = 'Tidak ditemukan.';
            return;
        }

        hasilCari.replaceChildren();
        const info = document.createElement('span');
        info.className = 'mr-2 text-slate-500';
        info.textContent = `${cocok.length} halaman:`;
        hasilCari.appendChild(info);

        cocok.forEach((n) => {
            const tombol = document.createElement('button');
            tombol.type = 'button';
            tombol.className =
                'mr-1 rounded bg-korps-100 px-1.5 py-0.5 text-xs text-korps-800 hover:bg-korps-600 hover:text-white';
            tombol.textContent = n;
            tombol.addEventListener('click', () => lompatKe(n));
            hasilCari.appendChild(tombol);
        });
    }

    // --- Kendali antarmuka ---
    document.getElementById('tombol-sebelumnya')
        ?.addEventListener('click', () => lompatKe(halamanSekarang - 1));
    document.getElementById('tombol-berikutnya')
        ?.addEventListener('click', () => lompatKe(halamanSekarang + 1));
    document.getElementById('tombol-perbesar')?.addEventListener('click', () => ubahZoom(0.2));
    document.getElementById('tombol-perkecil')?.addEventListener('click', () => ubahZoom(-0.2));

    inputHalaman?.addEventListener('change', (e) => lompatKe(e.target.value));

    let jedaCari;
    inputCari?.addEventListener('input', (e) => {
        clearTimeout(jedaCari);
        const nilai = e.target.value.trim();
        jedaCari = setTimeout(() => cariTeks(nilai), 400);
    });

    document.addEventListener('keydown', (e) => {
        if (e.target.matches('input, textarea')) return;
        if (e.key === 'ArrowRight' || e.key === 'PageDown') lompatKe(halamanSekarang + 1);
        if (e.key === 'ArrowLeft' || e.key === 'PageUp') lompatKe(halamanSekarang - 1);
    });

    // Dipakai halaman chat untuk membuka halaman sitasi (F-4.4).
    window.lompatKeHalaman = lompatKe;

    muat();
}
