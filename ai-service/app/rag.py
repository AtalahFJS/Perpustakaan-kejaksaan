"""Alur tanya jawab berbasis dokumen (PRD 7.6 dan 7.7).

Prinsip yang ditegakkan di sini:
- Jawaban disusun HANYA dari kutipan yang diambil sistem (F-6.2).
- Setiap klaim faktual merujuk nomor halaman yang dapat diklik (F-6.3).
- Bila kutipan tidak memuat jawabannya, sistem menyatakannya dan tidak
  mengarang (F-6.4, mitigasi R2).
"""

from __future__ import annotations

import logging
import re
from dataclasses import dataclass

from psycopg import Connection

from .embedding import embed_pertanyaan
from .konfigurasi import pengaturan
from .llm import PenyediaLlm

log = logging.getLogger(__name__)

PENANDA_TIDAK_DITEMUKAN = "TIDAK_DITEMUKAN"

# PRD 7.7 -- Prinsip Prompt.
INSTRUKSI_SISTEM = f"""\
Anda adalah asisten perpustakaan digital yang menjawab pertanyaan mengenai \
SATU dokumen tertentu.

Aturan yang wajib dipatuhi:
1. Jawab HANYA berdasarkan kutipan yang diberikan di bawah. Jangan memakai \
pengetahuan apa pun di luar kutipan tersebut.
2. Sertakan rujukan pada setiap pernyataan faktual dengan menulis nomor \
kutipan dalam kurung siku, misalnya [1] atau [2][5]. Nomor tersebut harus \
persis sama dengan nomor kutipan yang diberikan.
3. Bila kutipan tidak memuat jawaban atas pertanyaan, mulai jawaban Anda \
dengan kata {PENANDA_TIDAK_DITEMUKAN} lalu jelaskan secara singkat bahwa \
informasi itu tidak ditemukan di dalam dokumen. Jangan menebak dan jangan \
mengarang.
4. Jangan memberikan penafsiran, opini, atau nasihat hukum. Sajikan isi \
dokumen apa adanya.
5. Gunakan Bahasa Indonesia yang formal dan ringkas. Jawab langsung ke \
pokok persoalan tanpa kalimat pembuka yang bertele-tele.
"""


@dataclass(slots=True)
class PotonganDiambil:
    chunk_id: int
    isi: str
    halaman_awal: int
    halaman_akhir: int
    skor: float


@dataclass(slots=True)
class HasilTanya:
    jawaban: str
    sitasi: list[dict]
    ditemukan: bool
    tokens_in: int
    tokens_out: int
    latency_ms: int
    model: str


def ambil_potongan(
    conn: Connection, document_id: int, pertanyaan: str, batas: int | None = None
) -> list[PotonganDiambil]:
    """PRD 7.6 langkah 4: pencarian hibrida (vektor + kata kunci).

    Dua daftar peringkat digabung memakai Reciprocal Rank Fusion, sehingga
    potongan yang unggul secara makna maupun yang unggul secara kecocokan
    kata sama-sama berpeluang terambil.
    """
    cfg = pengaturan()
    batas = batas or cfg.jumlah_potongan_diambil
    vektor = embed_pertanyaan(pertanyaan)

    # Ambil kandidat lebih banyak dari kebutuhan akhir supaya penggabungan
    # peringkat punya ruang untuk bekerja.
    kandidat = batas * 4

    sql = """
        WITH per_vektor AS (
            SELECT id, content, page_start, page_end,
                   ROW_NUMBER() OVER (ORDER BY embedding <=> %(vektor)s) AS peringkat
            FROM chunks
            WHERE document_id = %(doc)s AND embedding IS NOT NULL
            ORDER BY embedding <=> %(vektor)s
            LIMIT %(kandidat)s
        ),
        per_kata AS (
            SELECT id, content, page_start, page_end,
                   ROW_NUMBER() OVER (
                       ORDER BY ts_rank(search_vector,
                                        plainto_tsquery('simple', %(kueri)s)) DESC
                   ) AS peringkat
            FROM chunks
            WHERE document_id = %(doc)s
              AND search_vector @@ plainto_tsquery('simple', %(kueri)s)
            LIMIT %(kandidat)s
        )
        SELECT
            COALESCE(v.id, k.id)                 AS id,
            COALESCE(v.content, k.content)       AS content,
            COALESCE(v.page_start, k.page_start) AS page_start,
            COALESCE(v.page_end, k.page_end)     AS page_end,
            COALESCE(1.0 / (60 + v.peringkat), 0) +
            COALESCE(1.0 / (60 + k.peringkat), 0) AS skor
        FROM per_vektor v
        FULL OUTER JOIN per_kata k ON k.id = v.id
        ORDER BY skor DESC
        LIMIT %(batas)s
    """

    with conn.cursor() as kursor:
        kursor.execute(
            sql,
            {
                "vektor": vektor,
                "doc": document_id,
                "kueri": pertanyaan,
                "kandidat": kandidat,
                "batas": batas,
            },
        )
        baris = kursor.fetchall()

    return [
        PotonganDiambil(
            chunk_id=b["id"],
            isi=b["content"],
            halaman_awal=b["page_start"],
            halaman_akhir=b["page_end"],
            skor=float(b["skor"]),
        )
        for b in baris
    ]


def _susun_kutipan(potongan_semua: list[PotonganDiambil]) -> str:
    """Menyusun blok kutipan bernomor lengkap dengan nomor halamannya."""
    bagian = []

    for nomor, p in enumerate(potongan_semua, start=1):
        halaman = (
            f"halaman {p.halaman_awal}"
            if p.halaman_awal == p.halaman_akhir
            else f"halaman {p.halaman_awal}-{p.halaman_akhir}"
        )
        bagian.append(f"[{nomor}] ({halaman})\n{p.isi}")

    return "\n\n".join(bagian)


def _ekstrak_sitasi(
    jawaban: str, potongan_semua: list[PotonganDiambil]
) -> list[dict]:
    """PRD 7.6 langkah 7: memetakan nomor kutipan ke nomor halaman.

    Hanya nomor yang benar-benar diberikan ke model yang diterima, sehingga
    model tidak dapat memunculkan rujukan halaman yang tidak ada.
    """
    ditemukan = re.findall(r"\[(\d{1,2})\]", jawaban)
    sitasi: list[dict] = []
    sudah: set[int] = set()

    for teks_nomor in ditemukan:
        nomor = int(teks_nomor)

        if not 1 <= nomor <= len(potongan_semua):
            continue

        potongan = potongan_semua[nomor - 1]
        if potongan.halaman_awal in sudah:
            continue

        sudah.add(potongan.halaman_awal)
        sitasi.append(
            {
                "nomor": nomor,
                "chunk_id": potongan.chunk_id,
                "halaman": potongan.halaman_awal,
                "halaman_akhir": potongan.halaman_akhir,
                # Cuplikan singkat untuk tooltip di antarmuka (F-3.6).
                "kutipan": " ".join(potongan.isi.split())[:240],
            }
        )

    return sorted(sitasi, key=lambda s: s["halaman"])


def tanya(
    conn: Connection,
    penyedia: PenyediaLlm,
    document_id: int,
    pertanyaan: str,
    riwayat: list[dict] | None = None,
    effort: str = "low",
) -> HasilTanya:
    potongan_semua = ambil_potongan(conn, document_id, pertanyaan)

    if not potongan_semua:
        return HasilTanya(
            jawaban=(
                "Informasi tersebut tidak ditemukan dalam dokumen ini. "
                "Tidak ada bagian dokumen yang relevan dengan pertanyaan Anda."
            ),
            sitasi=[],
            ditemukan=False,
            tokens_in=0,
            tokens_out=0,
            latency_ms=0,
            model="",
        )

    # PRD 7.6 langkah 5: instruksi sistem + kutipan + riwayat singkat + pertanyaan.
    pesan: list[dict] = []

    # F-6.6: pertanyaan lanjutan yang merujuk konteks sebelumnya.
    for giliran in (riwayat or [])[-6:]:
        peran = giliran.get("role")
        isi = (giliran.get("content") or "").strip()
        if peran in {"user", "assistant"} and isi:
            pesan.append({"role": peran, "content": isi})

    # Pastikan percakapan diawali giliran pengguna.
    while pesan and pesan[0]["role"] != "user":
        pesan.pop(0)

    pesan.append(
        {
            "role": "user",
            "content": (
                f"KUTIPAN DARI DOKUMEN:\n\n{_susun_kutipan(potongan_semua)}\n\n"
                f"PERTANYAAN:\n{pertanyaan}"
            ),
        }
    )

    hasil = penyedia.hasilkan(
        instruksi_sistem=INSTRUKSI_SISTEM,
        pesan=pesan,
        maks_token=8000,
        effort=effort,
    )

    if hasil.ditolak:
        return HasilTanya(
            jawaban=(
                "Pertanyaan ini tidak dapat diproses oleh penyedia AI karena "
                "kebijakan keamanannya. Silakan baca dokumen secara langsung "
                "melalui pembaca."
            ),
            sitasi=[],
            ditemukan=False,
            tokens_in=hasil.tokens_in,
            tokens_out=hasil.tokens_out,
            latency_ms=hasil.latency_ms,
            model=hasil.model,
        )

    jawaban = hasil.teks.strip()
    ditemukan = not jawaban.upper().startswith(PENANDA_TIDAK_DITEMUKAN)

    # Penanda internal dibuang; antarmuka menampilkan statusnya sendiri.
    if not ditemukan:
        jawaban = jawaban[len(PENANDA_TIDAK_DITEMUKAN) :].strip(" :-—")

    return HasilTanya(
        jawaban=jawaban,
        sitasi=_ekstrak_sitasi(jawaban, potongan_semua) if ditemukan else [],
        ditemukan=ditemukan,
        tokens_in=hasil.tokens_in,
        tokens_out=hasil.tokens_out,
        latency_ms=hasil.latency_ms,
        model=hasil.model,
    )
