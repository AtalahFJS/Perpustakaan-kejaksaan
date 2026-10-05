/**
 * Konfirmasi bertingkat untuk tindakan yang menghapus.
 *
 * Dipakai pada penghapusan kategori: langkah pertama menanyakan kategorinya,
 * langkah kedua hanya muncul bila kategori itu memuat dokumen dan menyebut
 * jumlahnya secara eksplisit -- supaya jumlah dokumen yang ikut terhapus
 * tidak pernah menjadi kejutan.
 *
 * Formulir menandai dirinya lewat atribut data:
 *   data-konfirmasi        -- pertanyaan langkah pertama (wajib)
 *   data-konfirmasi-kedua  -- pertanyaan langkah kedua (opsional)
 *
 * Bila JavaScript mati, formulir tetap terkirim tanpa dialog; server
 * menolaknya karena penegasan `konfirmasi_hapus_dokumen` tidak terisi --
 * jadi tidak ada dokumen yang terhapus diam-diam.
 */
export function pasangKonfirmasiHapus(akar = document) {
    akar.addEventListener('submit', (peristiwa) => {
        const formulir = peristiwa.target;
        if (!(formulir instanceof HTMLFormElement) || !formulir.dataset.konfirmasi) {
            return;
        }

        if (!window.confirm(formulir.dataset.konfirmasi)) {
            peristiwa.preventDefault();

            return;
        }

        const kedua = formulir.dataset.konfirmasiKedua;
        if (kedua && !window.confirm(kedua)) {
            peristiwa.preventDefault();
        }
    });
}
