/**
 * Antarmuka chat dengan dokumen (PRD 5.6).
 *
 * Setiap jawaban ditampilkan bersama sitasi halaman yang dapat diklik
 * (F-6.3); mengekliknya membuka pembaca pada halaman tersebut (F-4.4).
 */

export function pasangChat(opsi) {
    const { urlTanya, urlBersihkan, urlPembaca, urlUmpanBalik, csrf } = opsi;

    const daftar = document.getElementById('daftar-pesan');
    const form = document.getElementById('form-tanya');
    const input = document.getElementById('input-pertanyaan');
    const tombolKirim = document.getElementById('tombol-kirim');
    const sisaKuotaEl = document.getElementById('sisa-kuota');

    function gulirKeBawah() {
        daftar.scrollTop = daftar.scrollHeight;
    }

    function buatGelembungPengguna(teks) {
        const baris = document.createElement('div');
        baris.className = 'flex justify-end';
        baris.innerHTML = `
            <div class="max-w-[80%] rounded-2xl rounded-br-sm bg-korps-700 px-4 py-2.5 text-sm text-white">
                ${escapeHtml(teks)}
            </div>`;
        return baris;
    }

    function buatIndikatorMenunggu() {
        const baris = document.createElement('div');
        baris.id = 'indikator-menunggu';
        baris.className = 'flex justify-start';
        baris.innerHTML = `
            <div class="flex items-center gap-2 rounded-2xl rounded-bl-sm bg-white px-4 py-3 text-sm text-slate-500 ring-1 ring-slate-200">
                <span class="flex gap-1">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:-0.3s]"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:-0.15s]"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400"></span>
                </span>
                Mencari jawaban di dalam dokumen…
            </div>`;
        return baris;
    }

    /**
     * F-6.2 / F-6.3: jawaban ditampilkan bersama daftar sitasi halaman.
     * F-6.9: setiap jawaban diberi penanda bahwa isinya dihasilkan AI.
     */
    function buatGelembungJawaban(data) {
        const baris = document.createElement('div');
        baris.className = 'flex justify-start';

        const sitasi = (data.sitasi || [])
            .map(
                (s) => `
                <button type="button" data-halaman="${s.halaman}"
                        title="${escapeHtml(s.kutipan || '')}"
                        class="tombol-sitasi rounded-md bg-violet-100 px-1.5 py-0.5 text-[11px] font-medium text-violet-800 hover:bg-violet-600 hover:text-white transition">
                    Hal. ${s.halaman}
                </button>`,
            )
            .join('');

        const blokSitasi = sitasi
            ? `<div class="mt-2.5 flex flex-wrap items-center gap-1 border-t border-slate-100 pt-2">
                   <span class="mr-1 text-[11px] text-slate-500">Rujukan:</span>${sitasi}
               </div>`
            : '';

        // F-6.4: sistem menyatakan eksplisit bila informasi tidak ditemukan.
        const catatanTidakDitemukan =
            data.ditemukan === false
                ? `<p class="mt-2 rounded-md bg-amber-50 px-2 py-1 text-[11px] text-amber-800">
                       Informasi ini tidak ditemukan di dalam dokumen.
                   </p>`
                : '';

        baris.innerHTML = `
            <div class="panel-ai max-w-[85%] rounded-2xl rounded-bl-sm bg-white px-4 py-3 ring-1 ring-slate-200">
                <div class="mb-1.5 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-medium text-violet-800">
                        Dihasilkan AI
                    </span>
                </div>
                <div class="isi-ai text-sm leading-relaxed text-slate-800">${renderMarkdown(data.jawaban)}</div>
                ${catatanTidakDitemukan}
                ${blokSitasi}
                <div class="mt-2 flex items-center gap-1.5 text-slate-400">
                    <button type="button" data-nilai="1" class="tombol-umpan rounded p-1 hover:bg-slate-100 hover:text-emerald-600" title="Jawaban ini membantu">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M7.5 17.5v-8l3.2-6.4a1.6 1.6 0 012.9 1.3L12.5 8h4a1.5 1.5 0 011.47 1.8l-1.2 6A1.5 1.5 0 0115.3 17H7.5zM5.5 9.5H3a1 1 0 00-1 1v6a1 1 0 001 1h2.5v-8z"/></svg>
                    </button>
                    <button type="button" data-nilai="-1" class="tombol-umpan rounded p-1 hover:bg-slate-100 hover:text-rose-600" title="Jawaban ini kurang tepat">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M12.5 2.5v8l-3.2 6.4a1.6 1.6 0 01-2.9-1.3L7.5 12h-4a1.5 1.5 0 01-1.47-1.8l1.2-6A1.5 1.5 0 014.7 3h7.8zM14.5 10.5H17a1 1 0 001-1v-6a1 1 0 00-1-1h-2.5v8z"/></svg>
                    </button>
                </div>
            </div>`;

        pasangAksiJawaban(baris, data.pesan_id);
        return baris;
    }

    function pasangAksiJawaban(baris, pesanId) {
        // F-4.4: klik sitasi membuka pembaca pada halaman tersebut.
        baris.querySelectorAll('.tombol-sitasi').forEach((tombol) => {
            tombol.addEventListener('click', () => {
                const halaman = tombol.dataset.halaman;
                window.open(`${urlPembaca}?halaman=${halaman}`, 'pembaca-pustakaai');
            });
        });

        // F-6.7: umpan balik suka / tidak suka.
        baris.querySelectorAll('.tombol-umpan').forEach((tombol) => {
            tombol.addEventListener('click', async () => {
                if (!pesanId) return;
                const nilai = Number(tombol.dataset.nilai);
                const sudahAktif = tombol.classList.contains('text-korps-700');

                baris.querySelectorAll('.tombol-umpan').forEach((t) =>
                    t.classList.remove('text-korps-700', 'bg-slate-100'),
                );
                if (!sudahAktif) tombol.classList.add('text-korps-700', 'bg-slate-100');

                await fetch(urlUmpanBalik.replace('__ID__', pesanId), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ nilai: sudahAktif ? 0 : nilai }),
                }).catch(() => {});
            });
        });
    }

    function buatGelembungGalat(pesan) {
        const baris = document.createElement('div');
        baris.className = 'flex justify-start';
        baris.innerHTML = `
            <div class="max-w-[85%] rounded-2xl rounded-bl-sm border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                ${escapeHtml(pesan)}
            </div>`;
        return baris;
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const pertanyaan = input.value.trim();
        if (pertanyaan.length < 3) return;

        input.value = '';
        input.style.height = 'auto';
        tombolKirim.disabled = true;

        daftar.appendChild(buatGelembungPengguna(pertanyaan));
        daftar.appendChild(buatIndikatorMenunggu());
        gulirKeBawah();

        try {
            const respons = await fetch(urlTanya, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ pertanyaan }),
            });

            document.getElementById('indikator-menunggu')?.remove();
            const data = await respons.json();

            if (!respons.ok) {
                daftar.appendChild(
                    buatGelembungGalat(data.galat || 'Terjadi kendala. Silakan coba lagi.'),
                );
            } else {
                daftar.appendChild(buatGelembungJawaban(data));
                if (sisaKuotaEl && typeof data.sisa_kuota === 'number') {
                    sisaKuotaEl.textContent = data.sisa_kuota;
                }
            }
        } catch (galat) {
            document.getElementById('indikator-menunggu')?.remove();
            daftar.appendChild(
                buatGelembungGalat(
                    'Tidak dapat menghubungi server. Periksa koneksi Anda lalu coba lagi.',
                ),
            );
        } finally {
            tombolKirim.disabled = false;
            gulirKeBawah();
            input.focus();
        }
    });

    // Textarea yang tinggi otomatis; Enter mengirim, Shift+Enter baris baru.
    input?.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
    });

    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    document.getElementById('tombol-bersihkan')?.addEventListener('click', async () => {
        if (!confirm('Hapus seluruh riwayat percakapan untuk dokumen ini?')) return;

        await fetch(urlBersihkan, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf },
        });
        daftar.replaceChildren();
    });

    // Pasang aksi pada pesan yang sudah dirender server (riwayat lama).
    document.querySelectorAll('[data-pesan-id]').forEach((baris) => {
        pasangAksiJawaban(baris, baris.dataset.pesanId);
    });

    gulirKeBawah();
}

function escapeHtml(teks) {
    const div = document.createElement('div');
    div.textContent = teks ?? '';
    return div.innerHTML;
}

/**
 * Markdown minimal: tebal, daftar berpoin, dan paragraf.
 * Sengaja dibatasi -- teks di-escape lebih dulu sehingga keluaran model
 * tidak pernah dapat menyuntikkan HTML (perlindungan XSS, NFR 6.2).
 */
function renderMarkdown(teks) {
    const aman = escapeHtml(teks);
    const baris = aman.split(/\r?\n/);
    let hasil = '';
    let dalamDaftar = false;

    for (const b of baris) {
        const bersih = b.trim();

        if (/^[-*•]\s+/.test(bersih)) {
            if (!dalamDaftar) {
                hasil += '<ul>';
                dalamDaftar = true;
            }
            hasil += `<li>${tebal(bersih.replace(/^[-*•]\s+/, ''))}</li>`;
            continue;
        }

        if (dalamDaftar) {
            hasil += '</ul>';
            dalamDaftar = false;
        }

        if (bersih !== '') hasil += `<p>${tebal(bersih)}</p>`;
    }

    if (dalamDaftar) hasil += '</ul>';
    return hasil || `<p>${aman}</p>`;
}

function tebal(teks) {
    return teks.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}
