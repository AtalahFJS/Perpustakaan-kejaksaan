<?php

namespace App\Http\Controllers;

use App\Models\BukuTamu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gerbang sebelum katalog (pembaruan 26 Agustus 2026).
 *
 * Pengunjung memilih dulu: masuk sebagai Pegawai, atau melanjutkan sebagai
 * Tamu dengan mengisi buku tamu. Identitas tamu dicatat agar lembaga tahu
 * siapa saja yang mengakses aplikasi ini.
 */
class BukuTamuController extends Controller
{
    /** Halaman pemilihan Tamu atau Pegawai. */
    public function gerbang(Request $request): RedirectResponse|View
    {
        // Yang sudah masuk tidak perlu melewati gerbang lagi.
        if ($request->user()) {
            return redirect()->route('katalog.index');
        }

        // Tamu yang sudah mengisi buku tamu langsung diteruskan.
        if ($request->session()->has(BukuTamu::KUNCI_SESI)) {
            return redirect()->route('publik.katalog.index');
        }

        return view('gerbang');
    }

    /** Formulir buku tamu. */
    public function form(Request $request): RedirectResponse|View
    {
        if ($request->session()->has(BukuTamu::KUNCI_SESI)) {
            return redirect()->route('publik.katalog.index');
        }

        return view('buku-tamu');
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            // Longgar sengaja: nomor telepon Indonesia ditulis bermacam cara
            // (+62, 62, 0, berspasi, bertanda hubung). Yang ditolak hanyalah
            // yang jelas bukan nomor.
            'no_telp' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{7,}$/'],
            'domisili' => ['required', 'string', 'max:255'],
        ], [
            'nama.required' => 'Nama wajib diisi.',
            'no_telp.required' => 'Nomor telepon wajib diisi.',
            'no_telp.regex' => 'Nomor telepon tidak dikenali. Contoh: 0812-3456-7890.',
            'domisili.required' => 'Domisili wajib diisi.',
        ]);

        $tamu = BukuTamu::create([
            ...$data,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $request->session()->put(BukuTamu::KUNCI_SESI, $tamu->id);

        return redirect()->route('publik.katalog.index')
            ->with('sukses', 'Selamat datang, '.$tamu->nama.'. Selamat menelusuri katalog.');
    }

    /** F-7.2: daftar kunjungan, hanya untuk admin. */
    public function daftar(): View
    {
        return view('admin.buku-tamu', [
            'tamu' => BukuTamu::latest()->paginate(30),
            'jumlahHariIni' => BukuTamu::whereDate('created_at', today())->count(),
        ]);
    }
}
