"""Konfigurasi layanan AI.

NFR 6.6: seluruh parameter penyedia LLM dibaca dari lingkungan sehingga
penyedia dan modelnya dapat diganti tanpa deploy ulang.
"""

from __future__ import annotations

from functools import lru_cache
from pathlib import Path

from pydantic_settings import BaseSettings, SettingsConfigDict


class Pengaturan(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    database_url: str = "postgresql://localhost/pustakaai"
    internal_token: str = ""

    # Penyedia LLM
    llm_provider: str = "deepseek"
    llm_model: str = "deepseek-chat"

    # Kunci masing-masing penyedia. Hanya milik penyedia yang aktif
    # yang benar-benar dibaca.
    deepseek_api_key: str = ""
    deepseek_base_url: str = "https://api.deepseek.com"
    anthropic_api_key: str = ""

    # Kedalaman penalaran. Hanya berlaku pada penyedia yang mendukungnya
    # (Anthropic); DeepSeek mengabaikannya -- lihat catatan di
    # app/llm/deepseek_penyedia.py.
    llm_effort_chat: str = "low"
    llm_effort_ringkasan: str = "medium"

    # Suhu pengambilan sampel. Sengaja rendah karena tugasnya menyarikan
    # isi kutipan, bukan mengarang -- menekan risiko halusinasi (R2).
    # Tidak dipakai penyedia Anthropic, yang menolak parameter ini.
    llm_temperature: float = 0.3

    # Tarif per juta token untuk laporan estimasi biaya (F-7.3).
    # WAJIB dicocokkan dengan halaman harga penyedia yang sedang dipakai.
    llm_harga_input_per_juta: float = 0.28
    llm_harga_output_per_juta: float = 0.42

    # Embedding lokal
    embedding_model: str = "intfloat/multilingual-e5-base"
    embedding_dimensi: int = 768
    embedding_batch: int = 32

    # Pemotongan
    ukuran_potongan: int = 600
    tumpang_tindih: int = 80

    # Pengambilan
    jumlah_potongan_diambil: int = 8

    laravel_storage_path: str = "../Perpustakaan-kejaksaan/storage/app/private"

    ocr_aktif: bool = True
    ocr_bahasa: str = "ind+eng"

    @property
    def penyimpanan(self) -> Path:
        return Path(self.laravel_storage_path).expanduser().resolve()


@lru_cache
def pengaturan() -> Pengaturan:
    return Pengaturan()
