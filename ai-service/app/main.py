"""Layanan AI PustakaAI (PRD bagian 7.1).

Tanggung jawab: ekstraksi teks, pemotongan, embedding, pencarian vektor,
penyusunan prompt, dan pemanggilan LLM. Autentikasi pengguna, otorisasi,
dan seluruh antarmuka ditangani aplikasi Laravel.

Layanan ini tidak boleh diekspos ke internet -- hanya dipanggil Laravel
lewat jaringan internal, dilindungi token bersama.
"""

from __future__ import annotations

import logging
from contextlib import asynccontextmanager
from datetime import datetime, timezone

from fastapi import Depends, FastAPI, Header, HTTPException, status
from pydantic import BaseModel, Field

from . import basisdata, ekstraksi, potongan as pemotong
from .embedding import embed_potongan, model as model_embedding
from .konfigurasi import pengaturan
from .llm import PenyediaLlm, buat_penyedia
from .rag import tanya as jalankan_tanya
from .rag import tanya_lintas as jalankan_tanya_lintas
from .ringkasan import PembuatRingkasan

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)-8s %(name)s: %(message)s",
)
log = logging.getLogger("pustakaai")

_penyedia: PenyediaLlm | None = None


@asynccontextmanager
async def daur_hidup(app: FastAPI):
    global _penyedia

    cfg = pengaturan()
    log.info("Menyiapkan layanan AI (penyedia LLM: %s)…", cfg.llm_provider)

    basisdata.kolam()

    try:
        _penyedia = buat_penyedia()
    except Exception as galat:  # noqa: BLE001
        # NFR 6.4: kegagalan layanan AI tidak boleh melumpuhkan perpustakaan.
        # Layanan tetap hidup agar /health menjawab, dan Laravel dapat
        # menampilkan mode degradasi dengan pesan yang jelas.
        log.error("Penyedia LLM gagal disiapkan: %s", galat)
        _penyedia = None

    # Muat model embedding di awal supaya permintaan pertama tidak lambat.
    try:
        model_embedding()
    except Exception as galat:  # noqa: BLE001
        log.error("Model embedding gagal dimuat: %s", galat)

    yield

    basisdata.tutup()


app = FastAPI(
    title="Perpustakaan Kejaksaan Tinggi Jawa Barat — Layanan AI",
    version="1.0.0",
    description=(
        "Ekstraksi dokumen, embedding lokal, ringkasan bertingkat, dan "
        "tanya jawab berbasis kutipan (RAG)."
    ),
    lifespan=daur_hidup,
)


def periksa_token(x_internal_token: str = Header(default="")) -> None:
    """Token bersama antara Laravel dan layanan ini."""
    diharapkan = pengaturan().internal_token

    if diharapkan and x_internal_token != diharapkan:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token internal tidak sah.",
        )


def penyedia_llm() -> PenyediaLlm:
    if _penyedia is None:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=(
                "Penyedia LLM belum dikonfigurasi. Periksa ANTHROPIC_API_KEY "
                "pada berkas .env layanan AI."
            ),
        )
    return _penyedia


# --------------------------------------------------------------------------
# Skema permintaan dan tanggapan
# --------------------------------------------------------------------------

class PermintaanIngest(BaseModel):
    document_id: int
    file_path: str


class TanggapanIngest(BaseModel):
    halaman: int
    potongan: int
    ringkasan: int
    sampul: str | None = None
    perlu_ocr: bool = False
    halaman_kosong: list[int] = Field(default_factory=list)


class PermintaanChat(BaseModel):
    document_id: int
    question: str = Field(min_length=1, max_length=2000)
    history: list[dict] = Field(default_factory=list)
    user_id: int | None = None


class PermintaanChatLintas(BaseModel):
    """Tanya-jawab atas beberapa dokumen sekaligus.

    `document_ids` wajib dikirim pemanggil dan sudah disaring menurut hak
    akses penanya. Layanan ini tidak menyusun daftarnya sendiri, sehingga
    tidak mungkin melebar ke dokumen yang tidak boleh dilihat.
    """

    document_ids: list[int] = Field(min_length=1, max_length=500)
    question: str = Field(min_length=1, max_length=2000)
    history: list[dict] = Field(default_factory=list)
    user_id: int | None = None


class TanggapanChat(BaseModel):
    jawaban: str
    sitasi: list[dict]
    ditemukan: bool
    tokens_in: int
    tokens_out: int
    latency_ms: int


class PermintaanRingkasan(BaseModel):
    document_id: int


# --------------------------------------------------------------------------
# Utilitas basis data
# --------------------------------------------------------------------------

def _catat_pemakaian(
    conn,
    *,
    user_id: int | None,
    document_id: int | None,
    operasi: str,
    model: str,
    tokens_in: int,
    tokens_out: int,
    biaya: float,
    latency_ms: int,
    berhasil: bool = True,
    pesan_galat: str | None = None,
) -> None:
    """F-7.3: mencatat setiap pemanggilan LLM beserta estimasi biayanya."""
    with conn.cursor() as kursor:
        kursor.execute(
            """
            INSERT INTO ai_usage_logs
                (user_id, document_id, operation, provider, model,
                 tokens_in, tokens_out, cost_estimate, latency_ms,
                 berhasil, error_message, created_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """,
            (
                user_id,
                document_id,
                operasi,
                pengaturan().llm_provider,
                model,
                tokens_in,
                tokens_out,
                biaya,
                latency_ms,
                berhasil,
                pesan_galat,
                datetime.now(timezone.utc),
            ),
        )


def _pastikan_boleh_diproses(conn, document_id: int) -> dict:
    """F-8.2: dokumen terbatas tidak pernah diproses AI.

    Pemeriksaan diulang di sini, bukan hanya di Laravel, supaya aturan ini
    tetap berlaku walau layanan dipanggil dari jalur lain.
    """
    with conn.cursor() as kursor:
        kursor.execute(
            "SELECT id, title, classification FROM documents "
            "WHERE id = %s AND deleted_at IS NULL",
            (document_id,),
        )
        dokumen = kursor.fetchone()

    if dokumen is None:
        raise HTTPException(status_code=404, detail="Dokumen tidak ditemukan.")

    if dokumen["classification"] == "terbatas":
        raise HTTPException(
            status_code=403,
            detail=(
                "Dokumen berklasifikasi terbatas tidak diproses AI dan isinya "
                "tidak pernah dikirim ke layanan pihak ketiga."
            ),
        )

    return dokumen


# --------------------------------------------------------------------------
# Titik akhir
# --------------------------------------------------------------------------

@app.get("/health")
def kesehatan() -> dict:
    """Dipakai Laravel untuk menampilkan status layanan (NFR 6.4)."""
    cfg = pengaturan()
    return {
        "status": "sehat",
        "penyedia": cfg.llm_provider,
        "model": cfg.llm_model,
        "penyedia_siap": _penyedia is not None,
        "model_embedding": cfg.embedding_model,
    }


@app.post("/ingest", response_model=TanggapanIngest, dependencies=[Depends(periksa_token)])
def ingest(permintaan: PermintaanIngest) -> TanggapanIngest:
    """Alur ingest dokumen -- PRD 7.5 langkah 5 sampai 9."""
    cfg = pengaturan()
    llm = penyedia_llm()

    with basisdata.koneksi() as conn:
        _pastikan_boleh_diproses(conn, permintaan.document_id)

        # Langkah 5: ekstraksi teks per halaman (+ OCR bila perlu).
        hasil = ekstraksi.ekstrak(permintaan.file_path)
        log.info(
            "Dokumen %s: %s halaman diekstrak (%s halaman tanpa teks).",
            permintaan.document_id,
            hasil.jumlah_halaman,
            len(hasil.halaman_kosong),
        )

        # Langkah 6: pemotongan dengan tumpang tindih, menyimpan nomor halaman.
        potongan_semua = pemotong.potong(hasil.halaman)
        if not potongan_semua:
            raise HTTPException(
                status_code=422,
                detail="Dokumen tidak menghasilkan potongan teks yang dapat diproses.",
            )

        # Langkah 7: embedding batch, lalu simpan ke tabel chunks.
        vektor_semua = embed_potongan([p.isi for p in potongan_semua])

        with conn.cursor() as kursor:
            # Ingest ulang mengganti seluruh turunan lama dokumen ini.
            kursor.execute("DELETE FROM chunks WHERE document_id = %s", (permintaan.document_id,))

            kursor.executemany(
                """
                INSERT INTO chunks
                    (document_id, chunk_index, content, page_start, page_end,
                     token_count, embedding, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                """,
                [
                    (
                        permintaan.document_id,
                        p.indeks,
                        p.isi,
                        p.halaman_awal,
                        p.halaman_akhir,
                        p.perkiraan_token,
                        vektor_semua[i],
                    )
                    for i, p in enumerate(potongan_semua)
                ],
            )
        conn.commit()

        log.info("Dokumen %s: %s potongan disimpan.", permintaan.document_id, len(potongan_semua))

        # Langkah 8: ringkasan bertingkat.
        pembuat = PembuatRingkasan(llm, cfg.llm_effort_ringkasan)

        try:
            ringkasan_semua = pembuat.jalankan(potongan_semua)
        except Exception as galat:  # noqa: BLE001
            _catat_pemakaian(
                conn,
                user_id=None,
                document_id=permintaan.document_id,
                operasi="ringkasan",
                model=pembuat.model or cfg.llm_model,
                tokens_in=pembuat.tokens_in,
                tokens_out=pembuat.tokens_out,
                biaya=llm.biaya(pembuat.tokens_in, pembuat.tokens_out),
                latency_ms=pembuat.latency_ms,
                berhasil=False,
                pesan_galat=str(galat)[:500],
            )
            conn.commit()
            raise HTTPException(
                status_code=502, detail=f"Pembuatan ringkasan gagal: {galat}"
            ) from galat

        _simpan_ringkasan(conn, permintaan.document_id, ringkasan_semua, pembuat.model)

        _catat_pemakaian(
            conn,
            user_id=None,
            document_id=permintaan.document_id,
            operasi="ringkasan",
            model=pembuat.model or cfg.llm_model,
            tokens_in=pembuat.tokens_in,
            tokens_out=pembuat.tokens_out,
            biaya=llm.biaya(pembuat.tokens_in, pembuat.tokens_out),
            latency_ms=pembuat.latency_ms,
        )
        conn.commit()

    # Langkah 9: sampul dari halaman pertama.
    sampul = ekstraksi.buat_sampul(permintaan.file_path, permintaan.document_id)

    return TanggapanIngest(
        halaman=hasil.jumlah_halaman,
        potongan=len(potongan_semua),
        ringkasan=len(ringkasan_semua),
        sampul=sampul,
        perlu_ocr=hasil.perlu_ocr,
        halaman_kosong=hasil.halaman_kosong[:50],
    )


@app.post("/summarize", dependencies=[Depends(periksa_token)])
def buat_ringkasan(permintaan: PermintaanRingkasan) -> dict:
    """F-5.6: membuat ulang ringkasan tanpa mengekstrak ulang dokumen."""
    cfg = pengaturan()
    llm = penyedia_llm()

    with basisdata.koneksi() as conn:
        _pastikan_boleh_diproses(conn, permintaan.document_id)

        with conn.cursor() as kursor:
            kursor.execute(
                "SELECT chunk_index, content, page_start, page_end, token_count "
                "FROM chunks WHERE document_id = %s ORDER BY chunk_index",
                (permintaan.document_id,),
            )
            baris = kursor.fetchall()

        if not baris:
            raise HTTPException(
                status_code=422,
                detail="Dokumen belum memiliki potongan teks. Jalankan ingest terlebih dahulu.",
            )

        potongan_semua = [
            pemotong.Potongan(
                indeks=b["chunk_index"],
                isi=b["content"],
                halaman_awal=b["page_start"],
                halaman_akhir=b["page_end"],
                perkiraan_token=b["token_count"],
            )
            for b in baris
        ]

        pembuat = PembuatRingkasan(llm, cfg.llm_effort_ringkasan)
        ringkasan_semua = pembuat.jalankan(potongan_semua)

        _simpan_ringkasan(conn, permintaan.document_id, ringkasan_semua, pembuat.model)

        _catat_pemakaian(
            conn,
            user_id=None,
            document_id=permintaan.document_id,
            operasi="ringkasan",
            model=pembuat.model or cfg.llm_model,
            tokens_in=pembuat.tokens_in,
            tokens_out=pembuat.tokens_out,
            biaya=llm.biaya(pembuat.tokens_in, pembuat.tokens_out),
            latency_ms=pembuat.latency_ms,
        )
        conn.commit()

    return {"ringkasan": len(ringkasan_semua)}


@app.post(
    "/chat-lintas",
    response_model=TanggapanChat,
    dependencies=[Depends(periksa_token)],
)
def chat_lintas(permintaan: PermintaanChatLintas) -> TanggapanChat:
    """Tanya-jawab menurut relevansi, mencakup beberapa dokumen."""
    cfg = pengaturan()
    llm = penyedia_llm()

    with basisdata.koneksi() as conn:
        # F-8.2 berlapis: sekalipun pemanggil sudah menyaring, dokumen yang
        # tidak diizinkan diproses AI tetap dibuang di sini.
        with conn.cursor() as kursor:
            kursor.execute(
                """
                SELECT id FROM documents
                WHERE id = ANY(%(ids)s) AND ai_allowed = TRUE AND deleted_at IS NULL
                """,
                {"ids": permintaan.document_ids},
            )
            boleh = [b["id"] for b in kursor.fetchall()]

        hasil = jalankan_tanya_lintas(
            conn,
            llm,
            boleh,
            permintaan.question,
            permintaan.history,
            effort=cfg.llm_effort_chat,
        )

        _catat_pemakaian(
            conn,
            user_id=permintaan.user_id,
            document_id=None,
            operasi="chat_lintas",
            model=hasil.model or cfg.llm_model,
            tokens_in=hasil.tokens_in,
            tokens_out=hasil.tokens_out,
            biaya=llm.biaya(hasil.tokens_in, hasil.tokens_out),
            latency_ms=hasil.latency_ms,
        )
        conn.commit()

    return TanggapanChat(
        jawaban=hasil.jawaban,
        sitasi=hasil.sitasi,
        ditemukan=hasil.ditemukan,
        tokens_in=hasil.tokens_in,
        tokens_out=hasil.tokens_out,
        latency_ms=hasil.latency_ms,
        model=hasil.model or cfg.llm_model,
    )


@app.post("/chat", response_model=TanggapanChat, dependencies=[Depends(periksa_token)])
def chat(permintaan: PermintaanChat) -> TanggapanChat:
    """Alur chat -- PRD 7.6."""
    cfg = pengaturan()
    llm = penyedia_llm()

    with basisdata.koneksi() as conn:
        # Langkah 2: pemeriksaan klasifikasi dokumen.
        _pastikan_boleh_diproses(conn, permintaan.document_id)

        hasil = jalankan_tanya(
            conn,
            llm,
            permintaan.document_id,
            permintaan.question,
            permintaan.history,
            effort=cfg.llm_effort_chat,
        )

        # Langkah 8: pemakaian token dicatat.
        _catat_pemakaian(
            conn,
            user_id=permintaan.user_id,
            document_id=permintaan.document_id,
            operasi="chat",
            model=hasil.model or cfg.llm_model,
            tokens_in=hasil.tokens_in,
            tokens_out=hasil.tokens_out,
            biaya=llm.biaya(hasil.tokens_in, hasil.tokens_out),
            latency_ms=hasil.latency_ms,
        )
        conn.commit()

    return TanggapanChat(
        jawaban=hasil.jawaban,
        sitasi=hasil.sitasi,
        ditemukan=hasil.ditemukan,
        tokens_in=hasil.tokens_in,
        tokens_out=hasil.tokens_out,
        latency_ms=hasil.latency_ms,
    )


def _simpan_ringkasan(conn, document_id: int, ringkasan_semua: list[dict], model: str) -> None:
    """Menyimpan ringkasan, mengganti yang lama untuk dokumen yang sama."""
    with conn.cursor() as kursor:
        kursor.execute("DELETE FROM summaries WHERE document_id = %s", (document_id,))

        kursor.executemany(
            """
            INSERT INTO summaries
                (document_id, type, scope_label, urutan, content, model_used,
                 is_approved, generated_at, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, TRUE, NOW(), NOW(), NOW())
            """,
            [
                (
                    document_id,
                    r["type"],
                    r.get("scope_label"),
                    r.get("urutan", 0),
                    r["content"],
                    model,
                )
                for r in ringkasan_semua
            ],
        )
