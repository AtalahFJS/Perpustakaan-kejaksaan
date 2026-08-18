#!/usr/bin/env bash
#
# Menjalankan seluruh komponen PustakaAI untuk pengembangan lokal:
#   - PostgreSQL + Redis (layanan latar)
#   - Layanan AI (FastAPI, porta 8001)
#   - Aplikasi web (Laravel, porta 8000)
#   - Worker antrean (memproses ingest dokumen)
#
# Hentikan seluruhnya dengan Ctrl+C.

set -euo pipefail

AKAR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL="$AKAR/Perpustakaan-kejaksaan"
AI="$AKAR/ai-service"

export PATH="/opt/homebrew/opt/postgresql@17/bin:$PATH"

biru()  { printf '\033[1;34m%s\033[0m\n' "$1"; }
merah() { printf '\033[1;31m%s\033[0m\n' "$1"; }

# --- Prasyarat ---
biru "▸ Memeriksa layanan latar…"

if ! pg_isready -q 2>/dev/null; then
    biru "  PostgreSQL belum jalan, menyalakan…"
    brew services start postgresql@17 >/dev/null
    for _ in $(seq 1 30); do pg_isready -q && break; sleep 1; done
fi
pg_isready -q || { merah "PostgreSQL tidak dapat dinyalakan."; exit 1; }

if ! redis-cli ping >/dev/null 2>&1; then
    biru "  Redis belum jalan, menyalakan…"
    redis-server --daemonize yes --port 6379 --dir /tmp >/dev/null
    sleep 1
fi
redis-cli ping >/dev/null 2>&1 || { merah "Redis tidak dapat dinyalakan."; exit 1; }

[ -d "$AI/.venv" ] || { merah "Lingkungan Python belum disiapkan. Jalankan: python3 -m venv $AI/.venv && $AI/.venv/bin/pip install -r $AI/requirements.txt"; exit 1; }

# --- Bersihkan proses anak saat keluar ---
PID_SEMUA=()
bersihkan() {
    biru "\n▸ Menghentikan seluruh proses…"
    for pid in "${PID_SEMUA[@]:-}"; do
        [ -n "$pid" ] && kill "$pid" 2>/dev/null || true
    done
    wait 2>/dev/null || true
}
trap bersihkan EXIT INT TERM

# --- Layanan AI ---
biru "▸ Menyalakan layanan AI di http://127.0.0.1:8001 …"
(
    cd "$AI"
    exec ./.venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8001
) &
PID_SEMUA+=($!)

# --- Worker antrean ---
biru "▸ Menyalakan worker antrean…"
(
    cd "$LARAVEL"
    exec php artisan queue:work --tries=3 --timeout=1800
) &
PID_SEMUA+=($!)

# --- Aplikasi web ---
biru "▸ Menyalakan aplikasi web di http://127.0.0.1:8000 …"
(
    cd "$LARAVEL"
    exec php artisan serve --host=127.0.0.1 --port=8000
) &
PID_SEMUA+=($!)

sleep 2
biru ""
biru "══════════════════════════════════════════════════"
biru "  PustakaAI siap:  http://127.0.0.1:8000"
biru ""
biru "  admin      / rahasia123   (Administrator)"
biru "  pustakawan / rahasia123   (Pustakawan)"
biru "  pembaca    / rahasia123   (Pembaca)"
biru "══════════════════════════════════════════════════"
biru ""

wait
