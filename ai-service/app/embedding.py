"""Pembuatan vektor embedding secara lokal (PRD 7.3).

Embedding dipanggil ribuan kali saat ingest, sehingga menjalankannya di
mesin sendiri jauh lebih hemat daripada memakai API berbayar -- dan
berjalan cukup cepat di CPU.

R9: model yang dipakai bersifat multibahasa agar kualitas pencarian
dokumen berbahasa Indonesia tetap baik.
"""

from __future__ import annotations

import logging
import threading

import numpy as np

from .konfigurasi import pengaturan

log = logging.getLogger(__name__)

_model = None
_kunci = threading.Lock()


def model():
    """Memuat model sekali saja, aman dipanggil dari beberapa thread."""
    global _model

    if _model is None:
        with _kunci:
            if _model is None:
                from sentence_transformers import SentenceTransformer

                cfg = pengaturan()
                log.info("Memuat model embedding %s…", cfg.embedding_model)
                _model = SentenceTransformer(cfg.embedding_model, device="cpu")

                dimensi = _model.get_sentence_embedding_dimension()
                if dimensi != cfg.embedding_dimensi:
                    raise RuntimeError(
                        f"Model {cfg.embedding_model} menghasilkan vektor "
                        f"berdimensi {dimensi}, sedangkan kolom chunks.embedding "
                        f"berdimensi {cfg.embedding_dimensi}. Samakan "
                        "EMBEDDING_DIMENSI dan migrasi basis data."
                    )

                log.info("Model embedding siap (dimensi %s).", dimensi)

    return _model


def _bersihkan(teks: str) -> str:
    return " ".join(teks.split())


def embed_potongan(daftar_teks: list[str]) -> np.ndarray:
    """Membuat vektor untuk isi dokumen.

    Keluarga model E5 menuntut awalan peran pada teks; tanpa awalan ini
    kualitas pencocokan turun cukup jauh.
    """
    cfg = pengaturan()
    disiapkan = [f"passage: {_bersihkan(t)}" for t in daftar_teks]

    return model().encode(
        disiapkan,
        batch_size=cfg.embedding_batch,
        normalize_embeddings=True,
        show_progress_bar=False,
        convert_to_numpy=True,
    )


def embed_pertanyaan(pertanyaan: str) -> np.ndarray:
    """Membuat vektor untuk pertanyaan pengguna (PRD 7.6 langkah 3)."""
    hasil = model().encode(
        [f"query: {_bersihkan(pertanyaan)}"],
        normalize_embeddings=True,
        show_progress_bar=False,
        convert_to_numpy=True,
    )
    return hasil[0]
