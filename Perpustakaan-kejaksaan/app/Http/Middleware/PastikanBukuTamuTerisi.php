<?php

namespace App\Http\Middleware;

use App\Models\BukuTamu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga katalog publik: pengunjung harus melewati gerbang lebih dulu --
 * masuk sebagai pegawai, atau mengisi buku tamu sebagai tamu.
 *
 * Dipasang pada seluruh rute publik, bukan hanya halaman depannya, supaya
 * tautan langsung ke sebuah dokumen tidak menjadi jalan pintas melewati
 * pencatatan kunjungan.
 */
class PastikanBukuTamuTerisi
{
    public function handle(Request $request, Closure $next): Response
    {
        $sudahLewat = $request->user() !== null
            || $request->session()->has(BukuTamu::KUNCI_SESI);

        if (! $sudahLewat) {
            return redirect()->route('gerbang');
        }

        return $next($request);
    }
}
