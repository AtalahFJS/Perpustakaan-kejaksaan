"""Pembuatan ringkasan bertingkat (PRD 5.5 dan 7.5 langkah 8).

F-5.3: dokumen panjang diringkas dengan pendekatan bertingkat
(map-reduce) -- potongan dikelompokkan, tiap kelompok diringkas, lalu
seluruh ringkasan kelompok diringkas kembali menjadi ringkasan gabungan.
"""

from __future__ import annotations

import logging

from .llm import HasilLlm, PenyediaLlm
from .potongan import Potongan

log = logging.getLogger(__name__)

# Jumlah potongan per kelompok pada tahap "map".
POTONGAN_PER_KELOMPOK = 12

# Batas kelompok agar dokumen sangat tebal tidak meledakkan biaya.
MAKS_KELOMPOK = 24

_INSTRUKSI_DASAR = (
    "Anda adalah asisten yang meringkas dokumen untuk perpustakaan digital "
    "sebuah instansi pemerintah.\n"
    "Aturan yang wajib dipatuhi:\n"
    "- Ringkas HANYA berdasarkan teks yang diberikan. Jangan menambahkan "
    "informasi dari pengetahuan di luar teks.\n"
    "- Jangan memberikan penafsiran, opini, atau nasihat hukum. Sajikan "
    "isi dokumen apa adanya.\n"
    "- Gunakan Bahasa Indonesia yang formal dan ringkas.\n"
    "- Bila teks tidak cukup untuk diringkas, katakan demikian secara singkat."
)


class PembuatRingkasan:
    def __init__(self, penyedia: PenyediaLlm, effort: str) -> None:
        self._llm = penyedia
        self._effort = effort
        self.tokens_in = 0
        self.tokens_out = 0
        self.latency_ms = 0
        self.model = ""

    def _panggil(self, instruksi: str, permintaan: str, maks_token: int) -> str:
        hasil: HasilLlm = self._llm.hasilkan(
            instruksi_sistem=instruksi,
            pesan=[{"role": "user", "content": permintaan}],
            maks_token=maks_token,
            effort=self._effort,
        )

        self.tokens_in += hasil.tokens_in
        self.tokens_out += hasil.tokens_out
        self.latency_ms += hasil.latency_ms
        self.model = hasil.model or self.model

        if hasil.ditolak:
            raise RuntimeError(
                "Penyedia LLM menolak memproses dokumen ini "
                f"(kategori: {hasil.alasan_penolakan})."
            )

        return hasil.teks

    def jalankan(self, potongan_semua: list[Potongan]) -> list[dict]:
        """Menghasilkan ringkasan tiga tingkat (F-5.2).

        Mengembalikan daftar dict siap disimpan ke tabel `summaries`.
        """
        if not potongan_semua:
            return []

        kelompok = self._kelompokkan(potongan_semua)
        ringkasan_bagian: list[dict] = []

        # --- Tahap map: ringkas tiap kelompok potongan ---
        for urutan, (label, isi) in enumerate(kelompok):
            teks = self._panggil(
                _INSTRUKSI_DASAR
                + "\n\nTugas: ringkas bagian dokumen berikut dalam 2-4 kalimat "
                "yang memuat pokok isinya.",
                f"Bagian dokumen ({label}):\n\n{isi}",
                maks_token=4000,
            )

            ringkasan_bagian.append(
                {
                    "type": "per_bagian",
                    "scope_label": label,
                    "urutan": urutan,
                    "content": teks,
                }
            )

        gabungan = "\n\n".join(
            f"{r['scope_label']}: {r['content']}" for r in ringkasan_bagian
        )

        # --- Tahap reduce: ringkasan singkat dan poin-poin kunci ---
        singkat = self._panggil(
            _INSTRUKSI_DASAR
            + "\n\nTugas: dari kumpulan RINGKASAN BAGIAN berikut, tulis satu "
            "paragraf (maksimal 6 kalimat) yang menggambarkan isi dokumen "
            "secara menyeluruh. Keluarkan paragrafnya saja, tanpa judul.",
            gabungan,
            maks_token=3000,
        )

        poin = self._panggil(
            _INSTRUKSI_DASAR
            + "\n\nTugas: dari kumpulan RINGKASAN BAGIAN berikut, susun 5 sampai "
            "10 poin kunci. Tulis satu poin per baris, diawali tanda '-'. "
            "Jangan menambahkan judul atau kalimat pembuka.",
            gabungan,
            maks_token=3000,
        )

        return [
            {"type": "singkat", "scope_label": None, "urutan": 0, "content": singkat},
            {"type": "poin_kunci", "scope_label": None, "urutan": 0, "content": poin},
            *ringkasan_bagian,
        ]

    def _kelompokkan(self, potongan_semua: list[Potongan]) -> list[tuple[str, str]]:
        """Mengelompokkan potongan; kelompok diperbesar bila dokumen tebal."""
        per_kelompok = POTONGAN_PER_KELOMPOK

        # Jaga agar jumlah pemanggilan LLM tidak melebihi batas (R3: biaya API).
        if len(potongan_semua) / per_kelompok > MAKS_KELOMPOK:
            per_kelompok = -(-len(potongan_semua) // MAKS_KELOMPOK)

        kelompok: list[tuple[str, str]] = []

        for i in range(0, len(potongan_semua), per_kelompok):
            bagian = potongan_semua[i : i + per_kelompok]
            awal = bagian[0].halaman_awal
            akhir = bagian[-1].halaman_akhir
            label = f"Halaman {awal}" if awal == akhir else f"Halaman {awal}-{akhir}"
            kelompok.append((label, "\n\n".join(p.isi for p in bagian)))

        return kelompok
