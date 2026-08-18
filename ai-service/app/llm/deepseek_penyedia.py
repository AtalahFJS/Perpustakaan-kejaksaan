"""Penyedia LLM berbasis DeepSeek.

DeepSeek menyediakan antarmuka yang kompatibel dengan OpenAI, sehingga
dilayani memakai SDK `openai` dengan base_url yang diarahkan ke
https://api.deepseek.com -- sesuai anjuran dokumentasi resmi DeepSeek.

Perbedaan penting dengan penyedia Anthropic:

- Tidak ada parameter `effort`. Kedalaman penalaran ditentukan oleh
  pilihan model (`deepseek-chat` vs `deepseek-reasoner`), bukan oleh
  parameter permintaan. Argumen `effort` tetap diterima demi memenuhi
  kontrak PenyediaLlm, tetapi diabaikan.
- Instruksi sistem dikirim sebagai pesan pertama berperan "system",
  bukan sebagai kolom terpisah.
- Mendukung `temperature`, yang dipatok rendah karena tugasnya
  menyarikan isi kutipan dan bukan mengarang (mitigasi R2).
"""

from __future__ import annotations

import logging
import time

from openai import (
    APIConnectionError,
    APIStatusError,
    APITimeoutError,
    AuthenticationError,
    OpenAI,
    RateLimitError,
)

from ..konfigurasi import pengaturan
from . import HasilLlm

log = logging.getLogger(__name__)

# Batas keluaran deepseek-chat. Permintaan yang melebihinya ditolak API,
# jadi nilainya dipatok di sisi kita.
MAKS_TOKEN_KELUARAN = 8192


class PenyediaDeepSeek:
    nama = "deepseek"

    def __init__(self) -> None:
        cfg = pengaturan()

        if not cfg.deepseek_api_key:
            raise RuntimeError(
                "DEEPSEEK_API_KEY belum diisi. Isi berkas .env layanan AI, "
                "atau setel LLM_PROVIDER=palsu untuk menjalankan sistem tanpa "
                "memanggil API."
            )

        self._klien = OpenAI(
            api_key=cfg.deepseek_api_key,
            base_url=cfg.deepseek_base_url,
            # Ringkasan dokumen tebal dapat berjalan lama; batas waktu
            # dibuat longgar tetapi tetap ada agar tidak menggantung.
            timeout=300.0,
            max_retries=3,
        )
        self._model = cfg.llm_model
        self._suhu = cfg.llm_temperature
        self._harga_in = cfg.llm_harga_input_per_juta
        self._harga_out = cfg.llm_harga_output_per_juta

    def hasilkan(
        self,
        *,
        instruksi_sistem: str,
        pesan: list[dict],
        maks_token: int,
        effort: str,
    ) -> HasilLlm:
        mulai = time.perf_counter()

        # Instruksi sistem menjadi pesan pertama, lalu diikuti percakapan.
        pesan_lengkap = [{"role": "system", "content": instruksi_sistem}, *pesan]

        try:
            balasan = self._klien.chat.completions.create(
                model=self._model,
                messages=pesan_lengkap,
                max_tokens=min(maks_token, MAKS_TOKEN_KELUARAN),
                temperature=self._suhu,
                stream=False,
            )
        except AuthenticationError as galat:
            raise RuntimeError(
                "Kunci API DeepSeek ditolak. Periksa DEEPSEEK_API_KEY pada "
                "berkas .env layanan AI."
            ) from galat
        except RateLimitError as galat:
            raise RuntimeError(
                "Kuota atau laju permintaan DeepSeek terlampaui. Periksa saldo "
                "akun Anda di platform.deepseek.com, lalu coba lagi."
            ) from galat
        except (APIConnectionError, APITimeoutError) as galat:
            raise RuntimeError(
                "Layanan DeepSeek tidak dapat dihubungi. Periksa koneksi "
                "jaringan server."
            ) from galat
        except APIStatusError as galat:
            raise RuntimeError(
                f"DeepSeek menolak permintaan (HTTP {galat.status_code})."
            ) from galat

        latency_ms = int((time.perf_counter() - mulai) * 1000)

        pilihan = balasan.choices[0]
        pemakaian = balasan.usage

        # Penyaring konten penyedia dapat menghentikan jawaban di tengah
        # jalan. Diperlakukan sama seperti penolakan pada penyedia lain,
        # supaya lapisan di atasnya tidak perlu tahu penyedia mana yang aktif.
        if pilihan.finish_reason == "content_filter":
            log.warning("Permintaan ditolak penyaring konten DeepSeek")

            return HasilLlm(
                teks="",
                tokens_in=pemakaian.prompt_tokens if pemakaian else 0,
                tokens_out=pemakaian.completion_tokens if pemakaian else 0,
                latency_ms=latency_ms,
                model=balasan.model,
                ditolak=True,
                alasan_penolakan="penyaring konten penyedia",
            )

        if pilihan.finish_reason == "length":
            log.warning(
                "Jawaban terpotong karena mencapai batas token keluaran "
                "(%s token).",
                min(maks_token, MAKS_TOKEN_KELUARAN),
            )

        return HasilLlm(
            teks=(pilihan.message.content or "").strip(),
            tokens_in=pemakaian.prompt_tokens if pemakaian else 0,
            tokens_out=pemakaian.completion_tokens if pemakaian else 0,
            latency_ms=latency_ms,
            model=balasan.model,
            catatan={"finish_reason": pilihan.finish_reason},
        )

    def biaya(self, tokens_in: int, tokens_out: int) -> float:
        """F-7.3: estimasi biaya dalam USD."""
        return (
            tokens_in / 1_000_000 * self._harga_in
            + tokens_out / 1_000_000 * self._harga_out
        )
