import { pasangPembaca } from './pembaca.js';
import { pasangChat } from './chat.js';
import { pasangKategoriCepat } from './kategori-cepat.js';
import { pasangSemuaMenu } from './menu.js';
import { pasangKonfirmasiHapus } from './konfirmasi-hapus.js';
import { pasangAsisten } from './asisten.js';

// Dipanggil dari Blade pada halaman yang membutuhkannya.
window.PustakaAI = { pasangPembaca, pasangChat, pasangKategoriCepat };

// Menu bilah kepala ada di setiap halaman, jadi dipasang otomatis.
// Modul bertipe module sudah ditunda sampai dokumen selesai diurai, tapi
// penjagaan ini membuatnya tetap benar bila kelak dimuat dengan cara lain.
function pasangUmum() {
    pasangSemuaMenu();
    pasangKonfirmasiHapus();
    pasangAsisten();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pasangUmum);
} else {
    pasangUmum();
}
