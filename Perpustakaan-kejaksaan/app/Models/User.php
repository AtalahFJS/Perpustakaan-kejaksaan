<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Pembaruan 21 Agustus 2026: dua peran resmi setelah login (peran
    // ketiga, "User Umum", adalah pengunjung tanpa akun sama sekali --
    // lihat App\Http\Controllers\Publik\*).
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PEGAWAI = 'pegawai';

    public const SEMUA_ROLE = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_PEGAWAI => 'Pegawai',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPegawai(): bool
    {
        return $this->role === self::ROLE_PEGAWAI;
    }

    public function namaPeran(): string
    {
        return self::SEMUA_ROLE[$this->role] ?? $this->role;
    }

    /**
     * F-8.4: tingkat akses dokumen yang boleh dilihat pengguna ini.
     *
     * Pembaruan 23 Agustus 2026: tingkat "terbatas" (hanya admin)
     * ditiadakan. Tingkat akses kini diturunkan dari visibilitas kategori
     * yang hanya mengenal publik dan internal, sehingga Admin dan Pegawai
     * melihat kumpulan dokumen yang sama; yang membedakan keduanya adalah
     * kewenangan mengelola, bukan lagi cakupan yang terlihat.
     */
    public function klasifikasiYangBolehDilihat(): array
    {
        return [Document::KLASIFIKASI_PUBLIK, Document::KLASIFIKASI_INTERNAL];
    }

}
