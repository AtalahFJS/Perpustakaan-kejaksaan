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


# Instruksi untuk asisten katalog (lintas dokumen). Sengaja BERBEDA dari
# INSTRUKSI_SISTEM di atas: yang itu untuk tanya-jawab isi satu dokumen dan
# melarang keluar dari kutipan, sedangkan di sini tugasnya justru menimbang
# seluruh koleksi lalu merekomendasikan dokumen mana yang dicari pengguna.
INSTRUKSI_ASISTEN = """\
Anda adalah pustakawan digital pada Jaringan Dokumentasi dan Informasi Hukum \
Nasional. Tugas Anda membantu pengguna MENEMUKAN dokumen yang tepat di dalam \
koleksi, bukan sekadar mengutip isi satu dokumen.

Anda menerima dua hal: DAFTAR DOKUMEN (judul, penerbit, tahun, kategori) dan \
KUTIPAN dari sebagian isinya.

Aturan:
1. Rekomendasikan dokumen dari DAFTAR DOKUMEN. Jangan pernah menyebut dokumen \
yang tidak ada di daftar itu, dan jangan mengarang judul.
2. Sebut judul dokumen secara utuh saat merekomendasikan, dan jelaskan singkat \
mengapa dokumen itu relevan dengan yang dicari.
3. Bila menyatakan isi sebuah dokumen, rujuk kutipannya dengan nomor dalam \
kurung siku, misalnya [1] atau [2][5]. Untuk rekomendasi yang bersandar pada \
judul/kategori saja, nomor kutipan tidak perlu.
4. Pertanyaan tentang "yang terbaru" dijawab memakai tahun dan tanggal terbit \
pada daftar, bukan tanggal dokumen itu diunggah.
5. Bila tidak ada yang benar-benar cocok, katakan terus terang, lalu tawarkan \
dokumen yang paling mendekati beserta alasannya. Jangan menjawab dengan \
penolakan kosong.
6. Anda boleh menjawab pertanyaan lanjutan dengan menoleh ke percakapan \
sebelumnya.
7. Jangan memberi penafsiran atau nasihat hukum. Sajikan isi dokumen apa \
adanya, dan ingatkan pengguna memeriksa dokumen aslinya bila menyangkut \
ketentuan yang penting.
8. Gunakan Bahasa Indonesia yang formal dan ringkas.
"""


@dataclass(slots=True)
class PotonganDiambil:
    chunk_id: int
    isi: str
    halaman_awal: int
    halaman_akhir: int
    skor: float
    # Dokumen asal potongan ini. Selalu terisi; pada tanya-jawab satu
    # dokumen nilainya sama untuk semua potongan, sedangkan pada tanya
    # lintas-dokumen inilah yang membuat sitasi dapat menunjuk dokumennya.
    document_id: int = 0


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
    conn: Connection,
    document_ids: list[int],
    pertanyaan: str,
    batas: int | None = None,
) -> list[PotonganDiambil]:
    """PRD 7.6 langkah 4: pencarian hibrida (vektor + kata kunci).

    Dua daftar peringkat digabung memakai Reciprocal Rank Fusion, sehingga
    potongan yang unggul secara makna maupun yang unggul secara kecocokan
    kata sama-sama berpeluang terambil.

    `document_ids` adalah SATU-SATUNYA pembatas akses di lapisan ini: daftar
    itu disusun Laravel dari dokumen yang benar-benar boleh dilihat penanya
    dan yang diizinkan diproses AI. Layanan ini tidak menebak-nebak hak
    akses sendiri -- kalau daftarnya kosong, tidak ada yang diambil.
    """
    if not document_ids:
        return []

    cfg = pengaturan()
    batas = batas or cfg.jumlah_potongan_diambil
    vektor = embed_pertanyaan(pertanyaan)

    # Ambil kandidat lebih banyak dari kebutuhan akhir supaya penggabungan
    # peringkat punya ruang untuk bekerja.
    kandidat = batas * 4

    sql = """
        WITH per_vektor AS (
            SELECT id, document_id, content, page_start, page_end,
                   ROW_NUMBER() OVER (ORDER BY embedding <=> %(vektor)s) AS peringkat
            FROM chunks
            WHERE document_id = ANY(%(docs)s) AND embedding IS NOT NULL
            ORDER BY embedding <=> %(vektor)s
            LIMIT %(kandidat)s
        ),
        per_kata AS (
            SELECT id, document_id, content, page_start, page_end,
                   ROW_NUMBER() OVER (
                       ORDER BY ts_rank(search_vector,
                                        plainto_tsquery('simple', %(kueri)s)) DESC
                   ) AS peringkat
            FROM chunks
            WHERE document_id = ANY(%(docs)s)
              AND search_vector @@ plainto_tsquery('simple', %(kueri)s)
            LIMIT %(kandidat)s
        )
        SELECT
            COALESCE(v.id, k.id)                 AS id,
            COALESCE(v.document_id, k.document_id) AS document_id,
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
                "docs": document_ids,
                "kueri": pertanyaan,
                "kandidat": kandidat,
                "batas": batas,
            },
        )
        baris = kursor.fetchall()

    return [
        PotonganDiambil(
            chunk_id=b["id"],
            document_id=b["document_id"],
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
    sudah: set[tuple[int, int]] = set()

    for teks_nomor in ditemukan:
        nomor = int(teks_nomor)

        if not 1 <= nomor <= len(potongan_semua):
            continue

        potongan = potongan_semua[nomor - 1]
        kunci = (potongan.document_id, potongan.halaman_awal)
        if kunci in sudah:
            continue

        sudah.add(kunci)
        sitasi.append(
            {
                "nomor": nomor,
                "chunk_id": potongan.chunk_id,
                "document_id": potongan.document_id,
                "halaman": potongan.halaman_awal,
                "halaman_akhir": potongan.halaman_akhir,
                # Cuplikan singkat untuk tooltip di antarmuka (F-3.6).
                "kutipan": " ".join(potongan.isi.split())[:240],
            }
        )

    return sorted(sitasi, key=lambda s: (s["document_id"], s["halaman"]))


def tanya(
    conn: Connection,
    penyedia: PenyediaLlm,
    document_id: int,
    pertanyaan: str,
    riwayat: list[dict] | None = None,
    effort: str = "low",
) -> HasilTanya:
    potongan_semua = ambil_potongan(conn, [document_id], pertanyaan)

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


def _susun_kutipan_lintas(
    potongan_semua: list[PotonganDiambil], judul: dict[int, str]
) -> str:
    """Kutipan lintas-dokumen: tiap nomor menyebut dokumen asalnya.

    Tanpa judul di setiap kutipan, model tidak punya cara membedakan dua
    kutipan yang berasal dari peraturan berbeda, dan jawabannya berisiko
    mencampur ketentuan dari dokumen yang tidak berkaitan.
    """
    bagian = []

    for nomor, p in enumerate(potongan_semua, start=1):
        halaman = (
            f"halaman {p.halaman_awal}"
            if p.halaman_awal == p.halaman_akhir
            else f"halaman {p.halaman_awal}-{p.halaman_akhir}"
        )
        nama = judul.get(p.document_id, f"Dokumen #{p.document_id}")
        bagian.append(f'[{nomor}] "{nama}" ({halaman})\n{p.isi}')

    return "\n\n".join(bagian)


def _daftar_dokumen(conn: Connection, document_ids: list[int], batas: int = 60) -> tuple[str, dict[int, str]]:
    """Ringkasan katalog yang boleh dilihat penanya.

    Tanpa daftar ini asisten hanya melihat potongan teks, sehingga tidak dapat
    menjawab pertanyaan yang bersandar pada metadata -- misalnya "yang
    terbaru" -- maupun merekomendasikan dokumen yang potongannya kebetulan
    tidak terambil. Diurutkan dari yang paling baru dan dibatasi agar prompt
    tidak membengkak pada koleksi besar.
    """
    with conn.cursor() as kursor:
        kursor.execute(
            """
            SELECT d.id, d.title, d.author, d.year, d.tanggal_terbit,
                   c.name AS kategori
            FROM documents d
            LEFT JOIN categories c ON c.id = d.category_id
            WHERE d.id = ANY(%(ids)s) AND d.deleted_at IS NULL
            ORDER BY d.tanggal_terbit DESC NULLS LAST, d.year DESC NULLS LAST
            LIMIT %(batas)s
            """,
            {"ids": document_ids, "batas": batas},
        )
        baris = kursor.fetchall()

    judul = {b["id"]: b["title"] for b in baris}
    garis = []
    for b in baris:
        bagian = [f'"{b["title"]}"']
        if b["author"]:
            bagian.append(f'penerbit {b["author"]}')
        if b["tanggal_terbit"]:
            bagian.append(f'terbit {b["tanggal_terbit"]:%d/%m/%Y}')
        elif b["year"]:
            bagian.append(f'tahun {b["year"]}')
        if b["kategori"]:
            bagian.append(f'kategori {b["kategori"]}')
        garis.append("- " + ", ".join(bagian))

    return "\n".join(garis), judul


def tanya_lintas(
    conn: Connection,
    penyedia: PenyediaLlm,
    document_ids: list[int],
    pertanyaan: str,
    riwayat: list[dict] | None = None,
    effort: str = "low",
) -> HasilTanya:
    """Tanya-jawab atas beberapa dokumen sekaligus, menurut relevansi.

    `document_ids` sudah disaring Laravel menurut hak akses penanya; layanan
    ini tidak pernah melebarkan cakupannya sendiri.
    """
    if not document_ids:
        return HasilTanya(
            jawaban="Belum ada dokumen yang dapat ditelusuri.",
            sitasi=[], ditemukan=False, tokens_in=0, tokens_out=0,
            latency_ms=0, model="",
        )

    katalog, judul = _daftar_dokumen(conn, document_ids)
    potongan_semua = ambil_potongan(conn, document_ids, pertanyaan, batas=12)

    bagian = [f"DAFTAR DOKUMEN DALAM KOLEKSI:\n\n{katalog}"]
    if potongan_semua:
        bagian.append(
            "KUTIPAN DARI SEBAGIAN ISINYA:\n\n"
            + _susun_kutipan_lintas(potongan_semua, judul)
        )
    bagian.append(f"PERTANYAAN:\n{pertanyaan}")

    # F-6.6: pertanyaan lanjutan yang merujuk percakapan sebelumnya.
    pesan: list[dict] = []
    for giliran in (riwayat or [])[-6:]:
        peran = giliran.get("role")
        isi = (giliran.get("content") or "").strip()
        if peran in {"user", "assistant"} and isi:
            pesan.append({"role": peran, "content": isi})

    while pesan and pesan[0]["role"] != "user":
        pesan.pop(0)

    pesan.append({"role": "user", "content": "\n\n".join(bagian)})

    hasil = penyedia.hasilkan(
        instruksi_sistem=INSTRUKSI_ASISTEN,
        pesan=pesan,
        maks_token=8000,
        effort=effort,
    )

    if hasil.ditolak:
        return HasilTanya(
            jawaban=(
                "Pertanyaan ini tidak dapat diproses oleh penyedia AI karena "
                "kebijakan keamanannya. Silakan telusuri katalog secara langsung."
            ),
            sitasi=[],
            ditemukan=False,
            tokens_in=hasil.tokens_in,
            tokens_out=hasil.tokens_out,
            latency_ms=hasil.latency_ms,
            model=hasil.model,
        )

    jawaban = hasil.teks.strip()
    # Penanda internal dibuang bila model terlanjur memakainya. Titik ikut
    # dipangkas -- tanpa itu jawaban tampil diawali "." seperti pernah terjadi.
    if jawaban.upper().startswith(PENANDA_TIDAK_DITEMUKAN):
        jawaban = jawaban[len(PENANDA_TIDAK_DITEMUKAN) :].strip(" :-—.")

    sitasi = _ekstrak_sitasi(jawaban, potongan_semua)
    ditemukan = True
    for s in sitasi:
        s["judul"] = judul.get(s["document_id"], "")

    return HasilTanya(
        jawaban=jawaban,
        sitasi=sitasi,
        ditemukan=ditemukan,
        tokens_in=hasil.tokens_in,
        tokens_out=hasil.tokens_out,
        latency_ms=hasil.latency_ms,
        model=hasil.model,
    )
