"""Koneksi PostgreSQL bersama dengan aplikasi Laravel.

Satu basis data dikelola bersama (PRD 7.3): Laravel menulis metadata dan
status dokumen, layanan ini menulis potongan teks beserta vektornya.
"""

from __future__ import annotations

from contextlib import contextmanager
from typing import Iterator

from pgvector.psycopg import register_vector
from psycopg import Connection
from psycopg.rows import dict_row
from psycopg_pool import ConnectionPool

from .konfigurasi import pengaturan

_kolam: ConnectionPool | None = None


def _siapkan(conn: Connection) -> None:
    # Mendaftarkan adapter agar tipe `vector` dapat dibaca/ditulis
    # langsung sebagai array numpy.
    register_vector(conn)


def kolam() -> ConnectionPool:
    global _kolam
    if _kolam is None:
        _kolam = ConnectionPool(
            conninfo=pengaturan().database_url,
            min_size=1,
            max_size=8,
            open=True,
            configure=_siapkan,
            kwargs={"row_factory": dict_row},
        )
    return _kolam


@contextmanager
def koneksi() -> Iterator[Connection]:
    with kolam().connection() as conn:
        yield conn


def tutup() -> None:
    global _kolam
    if _kolam is not None:
        _kolam.close()
        _kolam = None
