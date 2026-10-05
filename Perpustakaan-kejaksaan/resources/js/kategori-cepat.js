/**
 * "Tambah kategori" ringkas di dalam formulir unggah/ubah dokumen.
 *
 * Kategori dikirim lewat fetch(), bukan dengan mengirim formulir biasa,
 * karena panel ini hidup di dalam <form> unggah dokumen yang sudah memuat
 * berkas PDF pilihan admin. Memuat ulang halaman akan mengosongkan input
 * berkas -- peramban tidak mengizinkan nilainya dipulihkan -- sekaligus
 * membuang metadata yang sudah diketik.
 */
export function pasangKategoriCepat({ urlSimpan, idPemicu, idPanel, idNama, idSimpan, idBatal, idGalat, idPilihan }) {
    const pemicu = document.getElementById(idPemicu);
    const panel = document.getElementById(idPanel);
    const nama = document.getElementById(idNama);
    const tombolSimpan = document.getElementById(idSimpan);
    const tombolBatal = document.getElementById(idBatal);
    const galat = document.getElementById(idGalat);
    const pilihan = document.getElementById(idPilihan);

    if (!pemicu || !panel || !nama || !tombolSimpan || !pilihan) {
        return;
    }

    const tampilkanGalat = (pesan) => {
        galat.textContent = pesan;
        galat.hidden = false;
    };

    const bersihkanGalat = () => {
        galat.textContent = '';
        galat.hidden = true;
    };

    const bukaPanel = (terbuka) => {
        panel.hidden = !terbuka;
        pemicu.setAttribute('aria-expanded', String(terbuka));
        if (terbuka) {
            nama.focus();
        } else {
            bersihkanGalat();
            nama.value = '';
        }
    };

    /** Daftar kategori diurutkan nama di server; sisipan baru mengikuti urutan itu. */
    const sisipkanTerurut = (opsi) => {
        const sesudah = Array.from(pilihan.options).find(
            (o) => o.value !== '' && o.textContent.localeCompare(opsi.textContent, 'id') > 0
        );
        pilihan.insertBefore(opsi, sesudah ?? null);
    };

    const simpan = async () => {
        const namaBaru = nama.value.trim();
        if (namaBaru === '') {
            tampilkanGalat('Nama kategori wajib diisi.');
            nama.focus();
            return;
        }

        bersihkanGalat();
        tombolSimpan.disabled = true;
        tombolSimpan.textContent = 'Menyimpan…';

        try {
            const jawaban = await fetch(urlSimpan, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ name: namaBaru }),
            });

            if (!jawaban.ok) {
                // 422 membawa pesan validasi Laravel; sisanya ditampilkan apa adanya.
                const isi = await jawaban.json().catch(() => ({}));
                const pesan = isi.errors
                    ? Object.values(isi.errors).flat().join(' ')
                    : (isi.message ?? 'Kategori gagal disimpan. Coba lagi.');
                tampilkanGalat(pesan);
                return;
            }

            const kategori = await jawaban.json();
            const opsi = new Option(kategori.label, kategori.id, false, true);
            sisipkanTerurut(opsi);
            pilihan.value = String(kategori.id);
            // Beritahu kode lain yang mungkin mengamati perubahan pilihan.
            pilihan.dispatchEvent(new Event('change', { bubbles: true }));
            bukaPanel(false);
            pemicu.focus();
        } catch {
            tampilkanGalat('Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.');
        } finally {
            tombolSimpan.disabled = false;
            tombolSimpan.textContent = 'Simpan kategori';
        }
    };

    pemicu.addEventListener('click', () => bukaPanel(panel.hidden));
    tombolBatal?.addEventListener('click', () => bukaPanel(false));
    tombolSimpan.addEventListener('click', simpan);

    // Enter di dalam panel harus menyimpan kategori, bukan mengirim
    // formulir unggah dokumen yang membungkusnya.
    nama.addEventListener('keydown', (peristiwa) => {
        if (peristiwa.key === 'Enter') {
            peristiwa.preventDefault();
            simpan();
        }
    });

    panel.addEventListener('keydown', (peristiwa) => {
        if (peristiwa.key === 'Escape') {
            peristiwa.preventDefault();
            bukaPanel(false);
            pemicu.focus();
        }
    });
}
