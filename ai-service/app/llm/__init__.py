"""Abstraksi penyedia LLM.

NFR 6.6 dan R6: aplikasi tidak pernah memanggil SDK penyedia secara
langsung. Seluruh pemanggilan melewati antarmuka `PenyediaLlm`, sehingga
penyedia dapat diganti lewat konfigurasi bila kebijakan instansi berubah
di tengah jalan.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Protocol


@dataclass(slots=True)
class HasilLlm:
    """Keluaran satu pemanggilan LLM beserta data pemakaiannya."""

    teks: str
    tokens_in: int = 0
    tokens_out: int = 0
    latency_ms: int = 0
    model: str = ""
    # Terisi bila penyedia menolak permintaan karena kebijakan keamanannya.
    ditolak: bool = False
    alasan_penolakan: str | None = None
    catatan: dict = field(default_factory=dict)


class PenyediaLlm(Protocol):
    """Kontrak yang harus dipenuhi setiap penyedia."""

    nama: str

    def hasilkan(
        self,
        *,
        instruksi_sistem: str,
        pesan: list[dict],
        maks_token: int,
        effort: str,
    ) -> HasilLlm: ...

    def biaya(self, tokens_in: int, tokens_out: int) -> float: ...


def buat_penyedia() -> PenyediaLlm:
    """Membuat penyedia sesuai konfigurasi LLM_PROVIDER."""
    from ..konfigurasi import pengaturan

    cfg = pengaturan()
    nama = cfg.llm_provider.strip().lower()

    if nama == "deepseek":
        from .deepseek_penyedia import PenyediaDeepSeek

        return PenyediaDeepSeek()

    if nama == "anthropic":
        from .anthropic_penyedia import PenyediaAnthropic

        return PenyediaAnthropic()

    if nama == "palsu":
        from .palsu import PenyediaPalsu

        return PenyediaPalsu()

    raise ValueError(
        f"Penyedia LLM '{nama}' tidak dikenali. "
        "Pilihan yang tersedia: deepseek, anthropic, palsu."
    )
