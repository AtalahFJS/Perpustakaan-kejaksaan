<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi rute menurut peran pengguna (F-1.2, F-8.4).
 * Dipakai sebagai `peran:admin` pada definisi rute. Sejak pembaruan
 * 21 Agustus 2026 hanya ada dua peran setelah login (admin, pegawai),
 * jadi kecocokan langsung terhadap kolom role sudah cukup -- tidak ada
 * lagi kasus "admin mewarisi kewenangan peran lain" seperti dulu.
 */
class PastikanPeran
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Anda harus masuk terlebih dahulu.');
        }

        if (! in_array($user->role, $peran, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}
