"""Penyedia LLM berbasis Anthropic Claude."""

from __future__ import annotations

import logging
import time

import anthropic

from ..konfigurasi import pengaturan
from . import HasilLlm

log = logging.getLogger(__name__)

# Kategori penolakan yang dialihkan otomatis ke model cadangan oleh API.
_BETA_FALLBACK = "server-side-fallback-2026-07-01"


class PenyediaAnthropic:
    nama = "anthropic"

    def __init__(self) -> None:
        cfg = pengaturan()

        if not cfg.anthropic_api_key:
            raise RuntimeError(
                "ANTHROPIC_API_KEY belum diisi. Isi berkas .env layanan AI, "
                "atau setel LLM_PROVIDER=palsu untuk menjalankan sistem tanpa "
                "memanggil API."
            )

        self._klien = anthropic.Anthropic(api_key=cfg.anthropic_api_key)
        self._model = cfg.llm_model
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

        # Dialirkan (streaming) supaya permintaan panjang tidak menabrak
        # batas waktu HTTP; jawaban utuh diambil lewat get_final_message().
        with self._klien.beta.messages.stream(
            model=self._model,
            max_tokens=maks_token,
            system=instruksi_sistem,
            messages=pesan,
            output_config={"effort": effort},
            # Bila klasifikasi keamanan menolak permintaan, API mengalihkan
            # ke model cadangan yang direkomendasikan alih-alih gagal.
            betas=[_BETA_FALLBACK],
            fallbacks="default",
        ) as aliran:
            balasan = aliran.get_final_message()

        latency_ms = int((time.perf_counter() - mulai) * 1000)

        # Periksa stop_reason sebelum membaca isi -- pada penolakan,
        # `content` bisa kosong.
        if balasan.stop_reason == "refusal":
            rincian = getattr(balasan, "stop_details", None)
            kategori = getattr(rincian, "category", None) if rincian else None
            log.warning("Permintaan ditolak penyedia LLM (kategori=%s)", kategori)

            return HasilLlm(
                teks="",
                tokens_in=balasan.usage.input_tokens,
                tokens_out=balasan.usage.output_tokens,
                latency_ms=latency_ms,
                model=balasan.model,
                ditolak=True,
                alasan_penolakan=kategori or "tidak disebutkan",
            )

        teks = "".join(
            blok.text for blok in balasan.content if getattr(blok, "type", "") == "text"
        )

        return HasilLlm(
            teks=teks.strip(),
            tokens_in=balasan.usage.input_tokens,
            tokens_out=balasan.usage.output_tokens,
            latency_ms=latency_ms,
            model=balasan.model,
        )

    def biaya(self, tokens_in: int, tokens_out: int) -> float:
        """F-7.3: estimasi biaya dalam USD."""
        return (
            tokens_in / 1_000_000 * self._harga_in
            + tokens_out / 1_000_000 * self._harga_out
        )
