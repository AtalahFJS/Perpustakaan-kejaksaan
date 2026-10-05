/**
 * Menu tarik-turun pada bilah kepala.
 *
 * Sebelumnya memakai <details>/<summary> bawaan peramban. Di Safari,
 * <summary> yang di-display flex tidak menutup kembali saat diklik ulang,
 * sehingga menu tamu terkunci terbuka. Kendali dipegang JS agar perilakunya
 * sama di semua peramban, sekaligus menambah dua hal yang tidak diberikan
 * <details>: menutup saat diklik di luar, dan saat menekan Esc.
 *
 * Seluruh menu di halaman dipasang sekaligus lewat penanda data-menu,
 * jadi Blade tidak perlu menuliskan pemasangan satu per satu.
 */
export function pasangSemuaMenu(akar = document) {
    const menu = [...akar.querySelectorAll('[data-menu]')].map(bungkus);
    if (menu.length === 0) {
        return;
    }

    // Satu pendengar untuk seluruh halaman, bukan satu per menu.
    document.addEventListener('click', (peristiwa) => {
        menu.forEach((m) => {
            if (m && !m.wadah.contains(peristiwa.target)) {
                m.setel(false);
            }
        });
    });

    document.addEventListener('keydown', (peristiwa) => {
        if (peristiwa.key !== 'Escape') {
            return;
        }
        menu.forEach((m) => {
            if (m && m.terbuka()) {
                m.setel(false);
                m.pemicu.focus();
            }
        });
    });
}

function bungkus(wadah) {
    const pemicu = wadah.querySelector('[data-menu-pemicu]');
    const panel = wadah.querySelector('[data-menu-panel]');
    if (!pemicu || !panel) {
        return null;
    }

    const terbuka = () => pemicu.getAttribute('aria-expanded') === 'true';

    const setel = (jadiTerbuka) => {
        pemicu.setAttribute('aria-expanded', String(jadiTerbuka));
        panel.hidden = !jadiTerbuka;
    };

    setel(false);

    pemicu.addEventListener('click', (peristiwa) => {
        // Menahan lompatan ke pendengar dokumen di atas, yang jika tidak
        // ditahan akan langsung menutup menu yang baru saja dibuka.
        peristiwa.stopPropagation();
        setel(!terbuka());
    });

    return { wadah, pemicu, panel, terbuka, setel };
}
