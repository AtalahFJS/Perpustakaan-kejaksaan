# Perpustakaan Kejaksaan Tinggi Jawa Barat — Sistem Perpustakaan Digital dengan Asisten AI

Implementasi dari **PRD v1.0 — Sistem Perpustakaan Digital dengan Asisten AI**
(`PRD-Perpustakaan-Digital-AI.pdf`).

Aplikasi web perpustakaan digital yang memungkinkan pengguna menyimpan, mencari,
dan membaca koleksi dokumen, dilengkapi asisten AI yang menghasilkan ringkasan
otomatis dan menjawab pertanyaan atas isi dokumen — selalu disertai rujukan
halaman yang dapat ditelusuri ke sumber aslinya.

---

## 1. Ruang lingkup yang dibangun

| Fase PRD | Cakupan | Status |
|---|---|---|
| **Fase 1 — Fondasi Perpustakaan** | Autentikasi, 3 peran, CRUD dokumen & metadata, unggah berkas, katalog, pencarian, filter, pembaca dalam peramban | Selesai |
| **Fase 2 — Pipeline & Ringkasan** | Ekstraksi teks, chunking, antrean & worker, status pemrosesan, ringkasan bertingkat | Selesai |
| **Fase 3 — Chat RAG** | Embedding & penyimpanan vektor, pencarian hibrida, antarmuka chat, sitasi yang dapat diklik | Selesai |
| **Fase 4 — Penyempurnaan** | Log audit, dasbor administrasi, laporan pemakaian token & biaya, ekspor CSV | Selesai |

Seluruh kebutuhan berprioritas **M (Must have)** pada PRD bagian 5 telah
diimplementasikan. Kebutuhan **S** sebagian besar juga tercakup.
Yang **belum** dikerjakan sesuai penandaan PRD: SSO (F‑1.6), autentikasi dua
faktor (F‑1.7), unggah massal (F‑2.8), rekomendasi dokumen serupa (F‑3.8),
anotasi pribadi (F‑4.6), dan chat lintas koleksi (F‑6.10, ditandai *Won't have*).

---

## 2. Arsitektur

Mengikuti PRD bagian 7.1 — dua servis dengan satu basis data bersama:

```
Peramban
   │ HTTPS
   ▼
Aplikasi Web (Laravel 13, porta 8000)
   • Autentikasi & RBAC        • Katalog, pencarian, pembaca
   • CRUD dokumen & metadata   • Antarmuka chat & ringkasan
   • Log audit                 • Orkestrasi antrean
   │ HTTP internal (token bersama)
   ▼
Layanan AI (FastAPI, porta 8001)
   • Ekstraksi teks (PyMuPDF + OCR)   • Chunking
   • Embedding lokal (CPU)            • Pencarian hibrida
   • Orkestrasi prompt                • Pemanggilan LLM
   │ HTTPS
   ▼
API LLM eksternal (DeepSeek — dapat diganti lewat konfigurasi)
```

Penyimpanan:

- **PostgreSQL 17 + pgvector** — data relasional *dan* vektor embedding dalam
  satu basis data (PRD 7.3: volume dokumen instansi masih jauh di bawah batas
  kemampuan pgvector).
- **Redis + worker antrean** — ingest dokumen tebal berjalan asinkron.
- **`storage/app/private`** — berkas PDF asli dan sampul. Direktori ini berada
  di luar *document root*, sehingga berkas **tidak pernah** dapat diakses lewat
  URL langsung (F‑4.3, NFR 6.2).

### Pembagian tanggung jawab

| Komponen | Tanggung jawab |
|---|---|
| Laravel | Antarmuka, autentikasi, otorisasi, CRUD, katalog, audit, antrean |
| Layanan AI (FastAPI) | Ekstraksi teks, chunking, embedding, pencarian vektor, penyusunan prompt, pemanggilan LLM |
| PostgreSQL + pgvector | Data relasional dan vektor dalam satu basis data |
| Redis + worker | Antrean pekerjaan ingest yang berjalan lama |
| API LLM eksternal | Pembuatan ringkasan dan jawaban chat |

---

## 3. Prasyarat

| Perangkat lunak | Versi minimum | Catatan |
|---|---|---|
| PHP | 8.3 | dengan ekstensi `pdo_pgsql`, `gd`, `zip`, `fileinfo`, `redis` |
| Composer | 2.7 | |
| Node.js | 20 | untuk membangun aset (Vite + Tailwind 4) |
| PostgreSQL | 17 | dengan ekstensi `vector` (pgvector) dan `pg_trgm` |
| Redis | 7 | antrean pekerjaan |
| Python | 3.12 – 3.14 | layanan AI |
| Tesseract | 5 | *opsional* — OCR untuk PDF hasil pindaian |

Pemasangan di macOS (Homebrew):

```bash
brew install postgresql@17 pgvector redis tesseract tesseract-lang
brew services start postgresql@17
brew services start redis
```

---

## 4. Pemasangan

### 4.1 Basis data

```bash
export PATH="/opt/homebrew/opt/postgresql@17/bin:$PATH"
createdb pustakaai
psql -d pustakaai -c "CREATE EXTENSION IF NOT EXISTS vector;"
psql -d pustakaai -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"
```

### 4.2 Aplikasi web (Laravel)

```bash
cd Perpustakaan-kejaksaan

composer install
cp .env.example .env          # bila .env belum ada
php artisan key:generate

# Sesuaikan DB_USERNAME / DB_PASSWORD pada .env, lalu:
php artisan migrate
php artisan db:seed           # akun demo, kategori, dan tag awal

npm install
npm run build
```

### 4.3 Layanan AI (FastAPI)

```bash
cd ai-service

python3 -m venv .venv
./.venv/bin/pip install -r requirements.txt

cp .env.example .env
```

Lalu sunting `ai-service/.env` dan isi kunci API penyedia yang dipakai.
Bawaannya DeepSeek — ambil kuncinya di <https://platform.deepseek.com/api_keys>:

```ini
LLM_PROVIDER=deepseek
LLM_MODEL=deepseek-chat
DEEPSEEK_API_KEY=sk-...
```

Untuk memakai Claude, ganti tiga baris itu menjadi:

```ini
LLM_PROVIDER=anthropic
LLM_MODEL=claude-opus-5
ANTHROPIC_API_KEY=sk-ant-...
```

Jangan lupa menyesuaikan `LLM_HARGA_INPUT_PER_JUTA` dan
`LLM_HARGA_OUTPUT_PER_JUTA` dengan tarif penyedia yang aktif, karena kedua nilai
itulah yang dipakai laporan estimasi biaya (F‑7.3).

> **Menjalankan tanpa kunci API.** Setel `LLM_PROVIDER=palsu`. Seluruh alur —
> ingest, chunking, embedding, sitasi, pencatatan pemakaian — tetap berjalan
> penuh; hanya teks ringkasan dan jawaban yang berupa keluaran tiruan. Berguna
> untuk demo dan pengujian tanpa biaya (mitigasi risiko R5 dan R6 pada PRD).

Pengunduhan model embedding (`intfloat/multilingual-e5-base`, ±1 GB) terjadi
otomatis pada penyalaan pertama.

`INTERNAL_TOKEN` di `ai-service/.env` **harus sama** dengan `AI_SERVICE_TOKEN`
di `Perpustakaan-kejaksaan/.env`.

---

## 5. Menjalankan

Cara termudah — satu perintah menyalakan semuanya:

```bash
./jalankan.sh
```

Atau secara manual di tiga terminal terpisah:

```bash
# Terminal 1 — layanan AI
cd ai-service && ./.venv/bin/python -m uvicorn app.main:app --port 8001

# Terminal 2 — worker antrean (wajib, agar dokumen diproses)
cd Perpustakaan-kejaksaan && php artisan queue:work --timeout=1800

# Terminal 3 — aplikasi web
cd Perpustakaan-kejaksaan && php artisan serve
```

Buka <http://127.0.0.1:8000>.

### Akun demo

| Nama pengguna | Kata sandi | Peran |
|---|---|---|
| `admin` | `rahasia123` | Administrator |
| `pustakawan` | `rahasia123` | Pustakawan |
| `pembaca` | `rahasia123` | Pembaca |

> **Ganti seluruh kata sandi ini sebelum dipakai di lingkungan sungguhan.**

---

## 6. Alur kerja

### Ingest dokumen (PRD 7.5)

1. Pustakawan mengunggah PDF dan mengisi metadata termasuk **klasifikasi**.
2. Berkas disimpan; status menjadi `uploaded`.
3. Bila klasifikasi **terbatas**, proses berhenti di sini — dokumen hanya masuk
   katalog dan isinya **tidak pernah** dikirim ke layanan AI (F‑8.2).
4. Pekerjaan ingest masuk antrean; status menjadi `processing`.
5. Worker mengekstrak teks per halaman dengan PyMuPDF. Halaman tanpa teks
   dijalankan melalui OCR bila Tesseract tersedia.
6. Teks dipecah menjadi potongan ±600 token dengan tumpang tindih ±80 token.
   Setiap potongan menyimpan nomor halaman asalnya.
7. Potongan diubah menjadi vektor embedding secara batch dan disimpan ke
   tabel `chunks`.
8. Ringkasan dibuat dengan pendekatan bertingkat (*map‑reduce*).
9. Sampul dibuat dari halaman pertama.
10. Status menjadi `ready`. Bila gagal di tahap mana pun, status menjadi
    `failed` dan pesan galatnya dicatat serta ditampilkan ke pustakawan, yang
    dapat memicu pemrosesan ulang dari antarmuka.

### Chat RAG (PRD 7.6)

1. Pengguna mengirim pertanyaan pada halaman dokumen.
2. Sistem memeriksa otorisasi dan klasifikasi dokumen.
3. Pertanyaan diubah menjadi vektor embedding.
4. Sistem mengambil 8 potongan paling relevan melalui **pencarian hibrida** —
   peringkat vektor dan peringkat kata kunci digabung dengan
   *Reciprocal Rank Fusion*.
5. Prompt disusun: instruksi sistem + kutipan bernomor beserta halamannya +
   riwayat percakapan singkat + pertanyaan.
6. Prompt dikirim ke API LLM yang sedang dikonfigurasi.
7. Nomor kutipan pada jawaban dipetakan kembali ke nomor halaman. **Hanya nomor
   yang benar‑benar diberikan ke model yang diterima**, sehingga model tidak
   dapat memunculkan rujukan halaman yang tidak ada.
8. Jawaban ditampilkan dengan tautan sitasi; mengekliknya membuka pembaca tepat
   pada halaman tersebut. Pemakaian token dicatat.

---

## 7. Bagaimana kebutuhan PRD dipenuhi

### Keamanan dan kerahasiaan

| Kebutuhan | Penerapan |
|---|---|
| F‑8.2 — dokumen terbatas tidak diproses AI | Diperiksa **tiga kali**: saat unggah (`DokumenController`), sebelum pekerjaan antrean dijalankan (`ProsesIngestDokumen`), dan di layanan AI itu sendiri (`_pastikan_boleh_diproses`). Mengubah klasifikasi menjadi terbatas akan **menghapus** seluruh potongan dan ringkasan yang sudah ada. |
| F‑4.3 — berkas tidak dapat diakses lewat URL langsung | Berkas berada di `storage/app/private`. Satu‑satunya jalur akses adalah `BerkasController`, yang memeriksa `DocumentPolicy` dan mencatat setiap akses. |
| NFR 6.2 — validasi tipe berkas berdasarkan isi | Angka ajaib `%PDF-` diperiksa langsung dari isi berkas, bukan sekadar ekstensinya. |
| NFR 6.2 — pembatasan laju | Endpoint login (5 percobaan / 5 menit per akun+IP) dan endpoint chat (20 permintaan / menit). |
| NFR 6.2 — XSS | Jawaban AI di‑*escape* lebih dulu, baru dirender sebagai Markdown terbatas — keluaran model tidak pernah dapat menyuntikkan HTML. |
| F‑1.4 — penonaktifan akun | Middleware `PastikanAkunAktif` memutus sesi pada permintaan berikutnya, tanpa menunggu sesi kedaluwarsa. |

### Ketahanan (NFR 6.4)

- Kegagalan layanan AI **tidak melumpuhkan** perpustakaan. Katalog, pencarian,
  pembaca, dan ringkasan yang sudah tersimpan tetap berjalan; antarmuka chat
  menampilkan pesan degradasi yang jelas.
- Pemanggilan layanan AI memakai *retry* dengan *exponential backoff*
  (3 percobaan, jeda 60 dan 300 detik pada level antrean).
- Dasbor administrasi menampilkan status kesehatan layanan AI secara langsung.

### Anti‑halusinasi (mitigasi R2)

- Instruksi sistem menuntut jawaban **hanya** dari kutipan yang diberikan.
- Setiap pernyataan faktual wajib menyertakan nomor kutipan.
- Bila kutipan tidak memuat jawabannya, model menandai jawabannya dengan
  penanda khusus dan antarmuka menampilkan pernyataan eksplisit bahwa informasi
  tidak ditemukan.
- Nomor sitasi divalidasi terhadap potongan yang benar‑benar dikirim.
- Setiap keluaran AI diberi penanda visual (lencana “Dihasilkan AI” dan panel
  berwarna) yang membedakannya dari teks asli dokumen.

---

## 8. Struktur proyek

```
Kejaksaan agung/
├── PRD-Perpustakaan-Digital-AI.pdf
├── jalankan.sh                     # menyalakan seluruh komponen
├── README.md
│
├── Perpustakaan-kejaksaan/         # Aplikasi web (Laravel 13)
│   ├── app/
│   │   ├── Http/Controllers/       # Katalog, Dokumen, Berkas, Pembaca, Chat, Admin
│   │   ├── Http/Middleware/        # PastikanPeran, PastikanAkunAktif
│   │   ├── Jobs/ProsesIngestDokumen.php
│   │   ├── Models/                 # Document, Chunk, Summary, ChatSession, …
│   │   ├── Policies/               # DocumentPolicy, SummaryPolicy
│   │   └── Services/               # KlienLayananAi, PencatatAudit
│   ├── config/pustakaai.php        # konfigurasi khusus aplikasi
│   ├── database/migrations/
│   ├── public/images/              # lambang Kejaksaan + ikon situs
│   ├── resources/
│   │   ├── js/pembaca.js           # pembaca PDF.js
│   │   ├── js/chat.js              # antarmuka chat + sitasi
│   │   └── views/                  # Blade + Tailwind 4
│   └── routes/web.php
│
└── ai-service/                     # Layanan AI (FastAPI)
    ├── app/
    │   ├── main.py                 # /health, /ingest, /summarize, /chat
    │   ├── ekstraksi.py            # PyMuPDF + OCR + sampul
    │   ├── potongan.py             # chunking dengan pelacakan halaman
    │   ├── embedding.py            # embedding lokal multibahasa
    │   ├── ringkasan.py            # ringkasan bertingkat (map-reduce)
    │   ├── rag.py                  # pencarian hibrida + prompt + sitasi
    │   ├── basisdata.py
    │   ├── konfigurasi.py
    │   └── llm/                    # abstraksi penyedia LLM
    │       ├── deepseek_penyedia.py
    │       ├── anthropic_penyedia.py
    │       └── palsu.py
    └── requirements.txt
```

---

## 9. Konfigurasi

### Mengganti penyedia LLM (NFR 6.6)

Aplikasi tidak pernah memanggil SDK penyedia secara langsung. Seluruh
pemanggilan melewati antarmuka `PenyediaLlm`, sehingga penyedia dapat diganti
lewat konfigurasi tanpa menyentuh kode aplikasi — mitigasi risiko R6.

Penyedia yang sudah tersedia:

| `LLM_PROVIDER` | Berkas | Kunci yang dibutuhkan | Catatan |
|---|---|---|---|
| `deepseek` | `deepseek_penyedia.py` | `DEEPSEEK_API_KEY` | Bawaan. Antarmuka kompatibel OpenAI. Tidak mengenal parameter `effort` — kedalaman penalaran ditentukan pilihan model |
| `anthropic` | `anthropic_penyedia.py` | `ANTHROPIC_API_KEY` | Mendukung `effort` dan pengalihan model otomatis saat permintaan ditolak |
| `palsu` | `palsu.py` | — | Mode uji tanpa biaya; seluruh alur tetap berjalan |

Berpindah antar penyedia cukup mengubah `LLM_PROVIDER`, `LLM_MODEL`, dan kedua
tarif harga di `.env`, lalu menyalakan ulang layanan AI. Tidak ada migrasi basis
data dan tidak ada perubahan kode — potongan teks serta vektor embedding tetap
dipakai apa adanya karena embedding dihasilkan lokal, terpisah dari penyedia LLM.

Menambahkan penyedia baru:

1. Buat kelas di `ai-service/app/llm/` yang memenuhi protokol `PenyediaLlm`
   (metode `hasilkan()` dan `biaya()`).
2. Daftarkan namanya pada `buat_penyedia()` di `app/llm/__init__.py`.
3. Setel `LLM_PROVIDER=<nama>` pada `.env`, lalu nyalakan ulang layanan AI.

### Parameter yang dapat disetel

| Variabel | Bawaan | Keterangan |
|---|---|---|
| `LLM_PROVIDER` | `deepseek` | Penyedia aktif: `deepseek`, `anthropic`, atau `palsu` |
| `LLM_MODEL` | `deepseek-chat` | Model yang dipakai. `deepseek-reasoner` lebih dalam penalarannya tetapi jauh lebih lambat |
| `LLM_TEMPERATURE` | `0.3` | Sengaja rendah — tugasnya menyarikan kutipan, bukan mengarang. Tidak dipakai penyedia Anthropic |
| `LLM_HARGA_INPUT_PER_JUTA` | `0.28` | Tarif untuk estimasi biaya. Sesuaikan dengan penyedia aktif |
| `LLM_HARGA_OUTPUT_PER_JUTA` | `0.42` | Idem |
| `LLM_EFFORT_CHAT` | `low` | Kedalaman penalaran chat. **Hanya berlaku pada Anthropic** — DeepSeek menentukannya lewat pilihan model |
| `LLM_EFFORT_RINGKASAN` | `medium` | Idem, untuk pembuatan ringkasan |
| `UKURAN_POTONGAN` | `600` | Ukuran potongan dalam token |
| `TUMPANG_TINDIH` | `80` | Tumpang tindih antar potongan |
| `JUMLAH_POTONGAN_DIAMBIL` | `8` | Potongan yang diambil per pertanyaan |
| `AI_QUOTA_CHAT_PER_HARI` | `50` | Kuota tanya jawab per pengguna per hari |
| `UPLOAD_MAX_MB` | `100` | Batas ukuran berkas unggahan |

### Mengganti model embedding

Bila mengganti `EMBEDDING_MODEL` dengan model berdimensi berbeda, kolom vektor
harus ikut diubah dan seluruh dokumen diproses ulang:

```sql
ALTER TABLE chunks DROP COLUMN embedding;
ALTER TABLE chunks ADD COLUMN embedding vector(<dimensi baru>);
CREATE INDEX chunks_embedding_hnsw_idx ON chunks
    USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64);
```

Layanan akan menolak menyala bila dimensi model dan dimensi kolom tidak cocok.

---

## 10. Pemecahan masalah

| Gejala | Penyebab dan penanganan |
|---|---|
| Dokumen berhenti di status *Menunggu diproses* | Worker antrean tidak berjalan. Jalankan `php artisan queue:work`. |
| Status *Gagal* dengan pesan “tidak dapat dihubungi” | Layanan AI mati. Periksa <http://127.0.0.1:8001/health>. |
| Status *Gagal* dengan pesan “tidak ada teks yang dapat diekstrak” | PDF berupa hasil pindaian tanpa lapisan teks. Pasang Tesseract (`brew install tesseract tesseract-lang`) lalu proses ulang. |
| Ringkasan kosong, chat menjawab teks bertanda “Mode uji tanpa LLM” | `LLM_PROVIDER` masih `palsu`. Setel ke `deepseek` dan isi `DEEPSEEK_API_KEY`. |
| “Kunci API DeepSeek ditolak” | Kunci salah atau sudah dicabut. Buat kunci baru di <https://platform.deepseek.com/api_keys>. |
| “Kuota atau laju permintaan DeepSeek terlampaui” | Saldo akun habis atau permintaan terlalu rapat. Periksa saldo di dasbor DeepSeek. |
| Penyalaan pertama layanan AI lambat | Model embedding sedang diunduh (±1 GB). Hanya terjadi sekali. |
| `ImportError: pyexpat … Symbol not found` saat membuat venv | Bawaan Homebrew Python 3.14 tertaut ke libexpat sistem yang lebih tua. Perbaiki dengan `brew install expat`, lalu arahkan ulang pustakanya (lihat catatan di bawah). |

<details>
<summary>Perbaikan pyexpat pada Homebrew Python 3.14 (macOS 26)</summary>

```bash
brew install expat
SO="$(brew --prefix python@3.14)/Frameworks/Python.framework/Versions/3.14/lib/python3.14/lib-dynload/pyexpat.cpython-314-darwin.so"
install_name_tool -change /usr/lib/libexpat.1.dylib "$(brew --prefix expat)/lib/libexpat.1.dylib" "$SO"
codesign --force --sign - "$SO"
```

Perlu diulang setelah Homebrew memperbarui `python@3.14`.
</details>

---

## 11. Serah terima

Sebelum sistem dioperasikan, hal‑hal berikut wajib dikerjakan:

1. **Ganti seluruh kata sandi akun demo** dan hapus akun yang tidak dipakai.
2. **Ganti `AI_SERVICE_TOKEN` / `INTERNAL_TOKEN`** dengan nilai acak yang kuat.
3. **Setel `APP_ENV=production` dan `APP_DEBUG=false`**, lalu jalankan
   `php artisan config:cache route:cache view:cache`.
4. **Aktifkan HTTPS.** Aplikasi memaksa skema HTTPS ketika `APP_ENV=production`.
5. **Batasi akses layanan AI** hanya dari alamat aplikasi web — layanan ini
   tidak boleh terjangkau dari internet.
6. **Konfirmasi kebijakan penyedia LLM** — tanpa penyimpanan data dan tanpa
   pelatihan ulang — lalu dokumentasikan sebagaimana diminta NFR 6.3.
7. **Siapkan pencadangan basis data harian** beserta direktori
   `storage/app/private`.
8. **Atur retensi log audit minimal 1 tahun** sesuai NFR 6.3.
9. Jalankan worker antrean sebagai layanan yang dipantau (`supervisor`,
   `systemd`, atau `launchd`), bukan proses manual.

### Pertanyaan terbuka pada PRD bagian 11

Butir‑butir berikut masih menunggu keputusan mentor dan memengaruhi konfigurasi:

- Jenis dokumen yang akan dimuat — memengaruhi rancangan metadata dan kategori.
- Kebijakan tertulis pengiriman dokumen ke layanan komputasi awan pihak ketiga.
- Perkiraan jumlah dokumen dan pengguna pada tahap awal.
- Ketersediaan sistem autentikasi internal untuk diintegrasikan (F‑1.6).
- Lokasi *deploy* — server instansi, VPS, atau layanan awan.
- Anggaran dan batas pemakaian API LLM — memengaruhi `AI_QUOTA_CHAT_PER_HARI`.
- Penanggung jawab pemeliharaan setelah masa magang berakhir.
