"""Pemotongan teks menjadi chunk (PRD 7.5 langkah 6).

Potongan berukuran +/- 600 token dengan tumpang tindih +/- 80 token.
Setiap potongan menyimpan rentang halaman asalnya, karena nomor halaman
inilah yang menjadi dasar sitasi jawaban AI (F-6.3).
"""

from __future__ import annotations

import re
from dataclasses import dataclass

from .ekstraksi import Halaman
from .konfigurasi import pengaturan

# Perkiraan kasar: 1 token ~ 4 karakter untuk teks Bahasa Indonesia.
# Cukup untuk menentukan batas potongan; penghitungan token yang
# sesungguhnya dilakukan penyedia LLM saat pemanggilan.
KARAKTER_PER_TOKEN = 4


@dataclass(slots=True)
class Potongan:
    indeks: int
    isi: str
    halaman_awal: int
    halaman_akhir: int
    perkiraan_token: int


def _pecah_kalimat(teks: str) -> list[str]:
    """Memecah pada batas kalimat agar potongan tidak terputus di tengah."""
    bagian = re.split(r"(?<=[.!?])\s+|\n{2,}", teks)
    return [b.strip() for b in bagian if b.strip()]


def potong(halaman_semua: list[Halaman]) -> list[Potongan]:
    cfg = pengaturan()
    maks_karakter = cfg.ukuran_potongan * KARAKTER_PER_TOKEN
    karakter_tindih = cfg.tumpang_tindih * KARAKTER_PER_TOKEN

    # Ratakan seluruh dokumen menjadi daftar (kalimat, nomor halaman)
    # supaya potongan dapat melintasi batas halaman dengan mulus sambil
    # tetap mengetahui halaman asal tiap kalimatnya.
    satuan: list[tuple[str, int]] = []
    for halaman in halaman_semua:
        if not halaman.teks.strip():
            continue
        for kalimat in _pecah_kalimat(halaman.teks):
            satuan.append((kalimat, halaman.nomor))

    if not satuan:
        return []

    hasil: list[Potongan] = []
    buffer: list[tuple[str, int]] = []
    panjang = 0

    def simpan() -> None:
        if not buffer:
            return
        isi = " ".join(k for k, _ in buffer).strip()
        if not isi:
            return
        halaman_nomor = [h for _, h in buffer]
        hasil.append(
            Potongan(
                indeks=len(hasil),
                isi=isi,
                halaman_awal=min(halaman_nomor),
                halaman_akhir=max(halaman_nomor),
                perkiraan_token=max(1, len(isi) // KARAKTER_PER_TOKEN),
            )
        )

    for kalimat, nomor in satuan:
        # Kalimat tunggal yang sangat panjang dipotong paksa agar tidak
        # menghasilkan satu potongan raksasa.
        if len(kalimat) > maks_karakter:
            simpan()
            buffer, panjang = [], 0
            for i in range(0, len(kalimat), maks_karakter):
                buffer = [(kalimat[i : i + maks_karakter], nomor)]
                simpan()
            buffer, panjang = [], 0
            continue

        if panjang + len(kalimat) > maks_karakter and buffer:
            simpan()

            # Bawa sebagian ekor potongan sebelumnya sebagai tumpang tindih,
            # supaya kalimat di batas potongan tidak kehilangan konteks.
            tindih: list[tuple[str, int]] = []
            terkumpul = 0
            for item in reversed(buffer):
                if terkumpul >= karakter_tindih:
                    break
                tindih.insert(0, item)
                terkumpul += len(item[0])

            buffer = tindih
            panjang = terkumpul

        buffer.append((kalimat, nomor))
        panjang += len(kalimat) + 1

    simpan()
    return hasil
