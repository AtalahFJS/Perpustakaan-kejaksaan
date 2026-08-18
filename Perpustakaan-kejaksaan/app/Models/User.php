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

    // F-1.2: tiga peran pengguna
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PUSTAKAWAN = 'pustakawan';
    public const ROLE_PEMBACA = 'pembaca';

    public const SEMUA_ROLE = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_PUSTAKAWAN => 'Pustakawan',
        self::ROLE_PEMBACA => 'Pembaca',
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

    /** Pustakawan mengelola koleksi; admin mewarisi seluruh kewenangannya. */
    public function isPustakawan(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_PUSTAKAWAN], true);
    }

    public function namaPeran(): string
    {
        return self::SEMUA_ROLE[$this->role] ?? $this->role;
    }

    /**
     * F-8.4: tingkat klasifikasi yang boleh dilihat pengguna ini.
     * Pembaca hanya melihat dokumen publik dan internal; dokumen
     * terbatas hanya untuk pustakawan dan admin.
     */
    public function klasifikasiYangBolehDilihat(): array
    {
        return $this->isPustakawan()
            ? [Document::KLASIFIKASI_PUBLIK, Document::KLASIFIKASI_INTERNAL, Document::KLASIFIKASI_TERBATAS]
            : [Document::KLASIFIKASI_PUBLIK, Document::KLASIFIKASI_INTERNAL];
    }
}
