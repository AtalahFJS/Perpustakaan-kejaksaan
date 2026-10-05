/**
 * Asisten dokumen mengambang.
 *
 * Tombol kecil di pojok kanan bawah; ditekan, panel percakapan terbuka.
 * Pertanyaan dijawab dari seluruh koleksi yang boleh dilihat penanya, dan
 * jawabannya selalu disertai judul serta tautan dokumen sumbernya --
 * itulah gunanya asisten ini: menunjukkan dokumen mana yang dicari.
 *
 * Panel dibiarkan hidup di DOM (bukan dibuat ulang tiap kali dibuka) agar
 * percakapan sebelumnya tidak hilang saat pengguna menutupnya sebentar.
 */
export function pasangAsisten() {
    const wadah = document.getElementById('asisten-mengambang');
    if (!wadah) {
        return;
    }

    const pemicu = document.getElementById('asisten-pemicu');
    const panel = document.getElementById('asisten-panel');
    const tutup = document.getElementById('asisten-tutup');
    const formulir = document.getElementById('asisten-form');
    const kotak = document.getElementById('asisten-pertanyaan');
    const tombol = document.getElementById('asisten-kirim');
    const percakapan = document.getElementById('asisten-percakapan');
    const url = wadah.dataset.url;

    /**
     * Riwayat percakapan, dikirim bersama tiap pertanyaan supaya pertanyaan
     * lanjutan seperti "yang terbaru mana?" punya konteks. Dipangkas ke enam
     * giliran terakhir agar biaya token tidak menumpuk sepanjang percakapan.
     */
    const riwayat = [];
    const catat = (role, content) => {
        riwayat.push({ role, content });
        if (riwayat.length > 6) {
            riwayat.splice(0, riwayat.length - 6);
        }
    };

    const setel = (terbuka) => {
        panel.hidden = !terbuka;
        pemicu.setAttribute('aria-expanded', String(terbuka));
        if (terbuka) {
            kotak.focus();
        }
    };

    setel(false);
    pemicu.addEventListener('click', () => setel(panel.hidden));
    tutup.addEventListener('click', () => {
        setel(false);
        pemicu.focus();
    });

    document.addEventListener('keydown', (peristiwa) => {
        if (peristiwa.key === 'Escape' && !panel.hidden) {
            setel(false);
            pemicu.focus();
        }
    });

    const keBawah = () => {
        percakapan.scrollTop = percakapan.scrollHeight;
    };

    /** Gelembung pertanyaan pengguna. */
    const tambahPertanyaan = (teks) => {
        const baris = document.createElement('div');
        baris.className = 'flex justify-end';
        const isi = document.createElement('p');
        isi.className = 'max-w-[85%] rounded-lg rounded-br-sm bg-korps-700 px-3 py-2 text-sm text-white';
        isi.textContent = teks;
        baris.appendChild(isi);
        percakapan.appendChild(baris);
        keBawah();
    };

    /**
     * Gelembung jawaban asisten. Selalu bertanda AI (NFR 6.5), dan daftar
     * dokumen sumber ditaruh di bawahnya sebagai tautan yang dapat dibuka.
     */
    const tambahJawaban = ({ teks, sumber = [], galat = false }) => {
        const baris = document.createElement('div');
        baris.className = 'flex justify-start';

        const kartu = document.createElement('div');
        kartu.className = galat
            ? 'max-w-[85%] rounded-lg rounded-bl-sm bg-rose-50 px-3 py-2 text-sm text-rose-800'
            : 'panel-ai max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-sm text-slate-800';

        const isi = document.createElement('p');
        // textContent, bukan innerHTML: jawaban model tidak pernah
        // disisipkan sebagai markup.
        isi.textContent = teks;
        isi.className = 'whitespace-pre-line';
        kartu.appendChild(isi);

        if (sumber.length > 0) {
            const judulBagian = document.createElement('p');
            judulBagian.className = 'mt-2 border-t border-violet-200 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500';
            judulBagian.textContent = 'Dokumen terkait';
            kartu.appendChild(judulBagian);

            const daftar = document.createElement('ul');
            daftar.className = 'mt-1 space-y-1';

            // Satu dokumen cukup muncul sekali walau dikutip di banyak halaman.
            const terpakai = new Set();
            sumber.forEach((s) => {
                if (!s.judul || terpakai.has(s.document_id)) {
                    return;
                }
                terpakai.add(s.document_id);

                const butir = document.createElement('li');
                const tautan = document.createElement('a');
                tautan.href = s.tautan ?? '#';
                tautan.className = 'text-korps-700 hover:underline';
                tautan.textContent = s.judul;
                butir.appendChild(tautan);

                const halaman = document.createElement('span');
                halaman.className = 'text-xs text-slate-500';
                halaman.textContent = ` — hal. ${s.halaman}`;
                butir.appendChild(halaman);

                daftar.appendChild(butir);
            });

            kartu.appendChild(daftar);
        }

        baris.appendChild(kartu);
        percakapan.appendChild(baris);
        keBawah();
    };

    formulir.addEventListener('submit', async (peristiwa) => {
        peristiwa.preventDefault();
        const pertanyaan = kotak.value.trim();
        if (pertanyaan === '') {
            return;
        }

        tambahPertanyaan(pertanyaan);
        kotak.value = '';
        tombol.disabled = true;

        const menunggu = document.createElement('p');
        menunggu.className = 'text-xs text-slate-500';
        menunggu.textContent = 'Menelusuri dokumen yang relevan…';
        percakapan.appendChild(menunggu);
        keBawah();

        try {
            const jawaban = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                // Riwayat dikirim apa adanya sebelum pertanyaan ini dicatat,
                // supaya pertanyaan yang sedang diajukan tidak terkirim dua kali.
                body: JSON.stringify({ pertanyaan, riwayat: [...riwayat] }),
            });

            const data = await jawaban.json().catch(() => ({}));
            menunggu.remove();

            if (!jawaban.ok) {
                tambahJawaban({
                    teks: data.galat ?? 'Pertanyaan gagal diproses. Coba lagi.',
                    galat: true,
                });

                return;
            }

            const teks = data.jawaban ?? '';
            tambahJawaban({ teks, sumber: data.sitasi ?? [] });
            catat('user', pertanyaan);
            catat('assistant', teks);
        } catch {
            menunggu.remove();
            tambahJawaban({
                teks: 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.',
                galat: true,
            });
        } finally {
            tombol.disabled = false;
            kotak.focus();
        }
    });
}
