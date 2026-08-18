"""Ekstraksi teks dan sampul dari berkas PDF (PRD 7.5 langkah 5 dan 9)."""

from __future__ import annotations

import logging
import shutil
from dataclasses import dataclass
from pathlib import Path

import pymupdf

from .konfigurasi import pengaturan

log = logging.getLogger(__name__)

# Halaman dengan teks lebih pendek dari ini dianggap hasil pindaian.
AMBANG_HALAMAN_KOSONG = 40


@dataclass(slots=True)
class Halaman:
    nomor: int  # 1-indeks, sesuai penomoran yang dilihat pengguna
    teks: str
    hasil_ocr: bool = False


@dataclass(slots=True)
class HasilEkstraksi:
    halaman: list[Halaman]
    jumlah_halaman: int
    # R4: dokumen bermasalah ditandai agar dapat ditinjau manual.
    perlu_ocr: bool
    halaman_kosong: list[int]


def _ocr_tersedia() -> bool:
    """Tesseract bersifat opsional; ketiadaannya tidak menggagalkan ingest."""
    return shutil.which("tesseract") is not None


def ekstrak(lintasan_pdf: str | Path) -> HasilEkstraksi:
    """Mengambil teks per halaman; menjalankan OCR bila halaman tanpa teks."""
    cfg = pengaturan()
    lintasan = Path(lintasan_pdf)

    if not lintasan.exists():
        raise FileNotFoundError(f"Berkas tidak ditemukan: {lintasan}")

    pakai_ocr = cfg.ocr_aktif and _ocr_tersedia()
    if cfg.ocr_aktif and not pakai_ocr:
        log.warning(
            "OCR diminta tetapi tesseract tidak terpasang. "
            "Halaman hasil pindaian akan dilewati dan ditandai."
        )

    halaman_semua: list[Halaman] = []
    halaman_kosong: list[int] = []

    with pymupdf.open(lintasan) as dokumen:
        for indeks, halaman in enumerate(dokumen, start=1):
            teks = halaman.get_text("text").strip()
            hasil_ocr = False

            if len(teks) < AMBANG_HALAMAN_KOSONG and pakai_ocr:
                try:
                    # Render pada 200 dpi agar hasil OCR cukup akurat.
                    tekspage = halaman.get_textpage_ocr(
                        language=cfg.ocr_bahasa, dpi=200, full=True
                    )
                    teks_ocr = halaman.get_text("text", textpage=tekspage).strip()
                    if len(teks_ocr) > len(teks):
                        teks, hasil_ocr = teks_ocr, True
                except Exception as galat:  # noqa: BLE001
                    log.warning("OCR halaman %s gagal: %s", indeks, galat)

            if len(teks) < AMBANG_HALAMAN_KOSONG:
                halaman_kosong.append(indeks)

            halaman_semua.append(Halaman(nomor=indeks, teks=teks, hasil_ocr=hasil_ocr))

    jumlah = len(halaman_semua)
    berisi = sum(1 for h in halaman_semua if len(h.teks) >= AMBANG_HALAMAN_KOSONG)

    if jumlah and berisi == 0:
        raise ValueError(
            "Tidak ada teks yang dapat diekstrak dari dokumen ini. "
            "Kemungkinan berupa hasil pindaian tanpa lapisan teks; "
            "jalankan OCR terlebih dahulu atau unggah versi PDF digital."
        )

    return HasilEkstraksi(
        halaman=halaman_semua,
        jumlah_halaman=jumlah,
        # Lebih dari seperempat halaman kosong -- layak ditinjau manual.
        perlu_ocr=bool(jumlah) and len(halaman_kosong) > jumlah * 0.25,
        halaman_kosong=halaman_kosong,
    )


def buat_sampul(lintasan_pdf: str | Path, document_id: int) -> str | None:
    """F-2.11: membuat sampul otomatis dari halaman pertama.

    Mengembalikan lintasan relatif terhadap disk penyimpanan Laravel,
    siap disimpan ke kolom `documents.cover_path`.
    """
    cfg = pengaturan()
    relatif = f"sampul/{document_id}.jpg"
    tujuan = cfg.penyimpanan / relatif
    tujuan.parent.mkdir(parents=True, exist_ok=True)

    try:
        with pymupdf.open(lintasan_pdf) as dokumen:
            if dokumen.page_count == 0:
                return None

            halaman = dokumen[0]
            # Zoom 1.6x menghasilkan sampul yang tajam pada tampilan katalog.
            gambar = halaman.get_pixmap(matrix=pymupdf.Matrix(1.6, 1.6))
            gambar.save(tujuan, jpg_quality=82)
    except Exception as galat:  # noqa: BLE001
        # Sampul bersifat pelengkap -- kegagalannya tidak membatalkan ingest.
        log.warning("Gagal membuat sampul dokumen %s: %s", document_id, galat)
        return None

    return relatif
