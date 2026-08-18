"""Penyedia tiruan.

Dipakai untuk menguji seluruh alur -- ingest, ringkasan, chat, sitasi,
pencatatan pemakaian -- tanpa memanggil API berbayar, dan sebagai mode
darurat bila kunci API belum tersedia (R6, dependensi 10.2).
"""

from __future__ import annotations

import re
import time

from . import HasilLlm

CATATAN = (
    "[Mode uji tanpa LLM] Setel LLM_PROVIDER=anthropic dan isi "
    "ANTHROPIC_API_KEY pada ai-service/.env untuk memakai model sungguhan."
)


class PenyediaPalsu:
    nama = "palsu"

    def hasilkan(
        self,
        *,
        instruksi_sistem: str,
        pesan: list[dict],
        maks_token: int,
        effort: str,
    ) -> HasilLlm:
        mulai = time.perf_counter()
        isi = pesan[-1]["content"] if pesan else ""

        # Blok kutipan bernomor hanya muncul pada permintaan chat, sehingga
        # keberadaannya adalah penanda jenis permintaan yang paling andal --
        # jauh lebih tepat daripada menebak dari kata-kata pada instruksi.
        label = re.findall(r"\[(\d+)\]\s*\(halaman", isi)

        if label:
            teks = self._jawaban_chat(label)
        elif "poin kunci" in instruksi_sistem.lower():
            teks = "\n".join(
                f"- {CATATAN.replace('[Mode uji tanpa LLM] ', '')}"
                if n == 1
                else f"- Poin kunci tiruan ke-{n}."
                for n in range(1, 6)
            )
        else:
            teks = f"Ringkasan tiruan atas potongan teks yang diberikan. {CATATAN}"

        return HasilLlm(
            teks=teks,
            tokens_in=len(isi) // 4,
            tokens_out=len(teks) // 4,
            latency_ms=int((time.perf_counter() - mulai) * 1000),
            model="palsu",
        )

    def _jawaban_chat(self, label: list[str]) -> str:
        """Meniru jawaban bersitasi agar alur sitasi dapat diuji utuh."""
        rujukan = "".join(f"[{n}]" for n in dict.fromkeys(label[:3]))

        return (
            f"Jawaban tiruan yang disusun dari kutipan {rujukan} pada dokumen ini. "
            f"{CATATAN}"
        )

    def biaya(self, tokens_in: int, tokens_out: int) -> float:
        return 0.0
