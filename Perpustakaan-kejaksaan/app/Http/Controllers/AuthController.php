<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\PencatatAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Modul autentikasi (PRD 5.1).
 */
class AuthController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function formMasuk(): View
    {
        return view('auth.masuk');
    }

    /**
     * F-1.1: masuk dengan email atau nama pengguna dan kata sandi.
     * NFR 6.2: endpoint login dibatasi lajunya.
     */
    public function masuk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Nama pengguna atau email wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $kunci = 'masuk:'.mb_strtolower($data['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $detik = RateLimiter::availableIn($kunci);

            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
            ]);
        }

        $kolom = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $kredensial = [$kolom => $data['username'], 'password' => $data['password']];

        if (! Auth::attempt($kredensial, $request->boolean('ingat'))) {
            RateLimiter::hit($kunci, 300);
            $this->catatRiwayat(null, $data['username'], false, $request);

            throw ValidationException::withMessages([
                'username' => 'Nama pengguna atau kata sandi tidak cocok.',
            ]);
        }

        // F-1.4: akun nonaktif tidak boleh masuk sama sekali.
        if (! $request->user()->is_active) {
            $pengguna = $request->user();
            Auth::logout();
            $this->catatRiwayat($pengguna, $data['username'], false, $request);

            throw ValidationException::withMessages([
                'username' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ]);
        }

        RateLimiter::clear($kunci);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        // F-1.5: catat waktu, IP, dan perangkat.
        $this->catatRiwayat($request->user(), $data['username'], true, $request);
        $this->audit->catat(PencatatAudit::MASUK);

        return redirect()->intended(route('katalog.index'));
    }

    public function keluar(Request $request): RedirectResponse
    {
        $this->audit->catat(PencatatAudit::KELUAR);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk')->with('sukses', 'Anda telah keluar.');
    }

    private function catatRiwayat(?User $pengguna, string $username, bool $berhasil, Request $request): void
    {
        LoginHistory::create([
            'user_id' => $pengguna?->id,
            'username_dicoba' => $username,
            'berhasil' => $berhasil,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
