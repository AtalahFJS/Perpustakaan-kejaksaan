<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi rute menurut peran pengguna (F-1.2, F-8.4).
 * Dipakai sebagai `peran:pustakawan` atau `peran:admin` pada definisi rute.
 */
class PastikanPeran
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Anda harus masuk terlebih dahulu.');
        }

        $diizinkan = match (true) {
            in_array(User::ROLE_ADMIN, $peran, true) && $user->isAdmin() => true,
            in_array(User::ROLE_PUSTAKAWAN, $peran, true) && $user->isPustakawan() => true,
            in_array($user->role, $peran, true) => true,
            default => false,
        };

        if (! $diizinkan) {
            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}
