<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu entri buku tamu: pengunjung yang mengisi identitas sebelum masuk
 * katalog publik.
 */
class BukuTamu extends Model
{
    /** Nama tabelnya Inggris mengikuti tabel lain; modelnya Indonesia. */
    protected $table = 'guest_books';

    /**
     * Penanda di sesi bahwa pengunjung ini sudah mengisi buku tamu.
     * Disimpan sebagai id entri agar dapat dirunut kembali bila perlu.
     */
    public const KUNCI_SESI = 'buku_tamu_id';

    protected $fillable = ['nama', 'no_telp', 'domisili', 'ip_address', 'user_agent'];
}
