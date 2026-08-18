<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\KlienLayananAi;
use App\Services\PencatatAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Modul administrasi dan audit (PRD 5.7).
 */
class AdminController extends Controller
{
    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly KlienLayananAi $ai,
    ) {}

    /** F-7.1: dasbor -- jumlah dokumen, status pemrosesan, pengguna aktif. */
    public function dasbor(): View
    {
        $perStatus = Document::query()
            ->select('status', DB::raw('count(*) as jumlah'))
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $pemakaian = AiUsageLog::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('coalesce(sum(tokens_in),0) as tokens_in')
            ->selectRaw('coalesce(sum(tokens_out),0) as tokens_out')
            ->selectRaw('coalesce(sum(cost_estimate),0) as biaya')
            ->selectRaw('count(*) as panggilan')
            ->first();

        return view('admin.dasbor', [
            'totalDokumen' => Document::count(),
            'perStatus' => $perStatus,
            'perKlasifikasi' => Document::query()
                ->select('classification', DB::raw('count(*) as jumlah'))
                ->groupBy('classification')
                ->pluck('jumlah', 'classification'),
            'totalPengguna' => User::count(),
            'penggunaAktif' => User::where('is_active', true)->count(),
            'penggunaLogin7Hari' => User::where('last_login_at', '>=', now()->subDays(7))->count(),
            'pemakaian' => $pemakaian,
            'layananAiSehat' => $this->ai->sehat(),
            'auditTerbaru' => AuditLog::with('user')->latest('created_at')->take(10)->get(),
        ]);
    }

    /** F-1.4: admin menambah, menonaktifkan, dan mengubah peran pengguna. */
    public function pengguna(Request $request): View
    {
        return view('admin.pengguna', [
            'pengguna' => User::query()
                ->when($request->filled('q'), fn ($q) => $q
                    ->where('name', 'ilike', '%'.$request->query('q').'%')
                    ->orWhere('email', 'ilike', '%'.$request->query('q').'%'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function simpanPengguna(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys(User::SEMUA_ROLE))],
        ], [
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'email.unique' => 'Email tersebut sudah terdaftar.',
            'username.unique' => 'Nama pengguna tersebut sudah dipakai.',
        ]);

        // Kata sandi di-hash otomatis lewat cast 'hashed' pada model User
        // (bcrypt, sesuai NFR 6.2).
        $pengguna = User::create($data);

        $this->audit->catat(PencatatAudit::PENGGUNA_BUAT, $pengguna, ['peran' => $pengguna->role]);

        return back()->with('sukses', 'Pengguna baru dibuat.');
    }

    public function ubahPengguna(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::SEMUA_ROLE))],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        // Cegah admin mengunci dirinya sendiri di luar sistem.
        if ($user->id === $request->user()->id
            && ((int) $data['is_active'] === 0 || $data['role'] !== User::ROLE_ADMIN)) {
            return back()->withErrors([
                'role' => 'Anda tidak dapat menurunkan peran atau menonaktifkan akun Anda sendiri.',
            ]);
        }

        if (blank($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        $this->audit->catat(PencatatAudit::PENGGUNA_UBAH, $user, [
            'peran' => $user->role,
            'aktif' => $user->is_active,
        ]);

        return back()->with('sukses', 'Data pengguna diperbarui.');
    }

    /** F-7.2: log audit setiap akses dokumen. */
    public function audit(Request $request): View
    {
        return view('admin.audit', [
            'log' => AuditLog::query()
                ->with('user')
                ->when($request->filled('aksi'), fn ($q) => $q->where('action', $request->query('aksi')))
                ->when($request->filled('pengguna'), fn ($q) => $q->where('user_id', $request->integer('pengguna')))
                ->latest('created_at')
                ->paginate(50)
                ->withQueryString(),
            'aksiSemua' => AuditLog::distinct()->orderBy('action')->pluck('action'),
            'penggunaSemua' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** F-7.3 dan F-7.4: log pemanggilan LLM dan laporan biaya per periode. */
    public function pemakaianAi(Request $request): View
    {
        $dari = $request->date('dari') ?? now()->subDays(30)->startOfDay();
        $sampai = $request->date('sampai') ?? now()->endOfDay();

        $dasar = AiUsageLog::query()->whereBetween('created_at', [$dari, $sampai]);

        return view('admin.pemakaian', [
            'dari' => $dari,
            'sampai' => $sampai,
            'ringkas' => (clone $dasar)
                ->selectRaw('operation')
                ->selectRaw('count(*) as panggilan')
                ->selectRaw('coalesce(sum(tokens_in),0) as tokens_in')
                ->selectRaw('coalesce(sum(tokens_out),0) as tokens_out')
                ->selectRaw('coalesce(sum(cost_estimate),0) as biaya')
                ->selectRaw('coalesce(round(avg(latency_ms)),0) as latensi_rata')
                ->groupBy('operation')
                ->get(),
            'perPengguna' => (clone $dasar)
                ->with('user')
                ->selectRaw('user_id')
                ->selectRaw('count(*) as panggilan')
                ->selectRaw('coalesce(sum(cost_estimate),0) as biaya')
                ->groupBy('user_id')
                ->orderByDesc('biaya')
                ->take(15)
                ->get(),
            'log' => (clone $dasar)->with(['user', 'document'])->latest('created_at')->paginate(30)->withQueryString(),
        ]);
    }

    /** F-7.7: ekspor log audit ke CSV. */
    public function eksporAudit(): StreamedResponse
    {
        $namaBerkas = 'log-audit-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $keluaran = fopen('php://output', 'w');
            fputcsv($keluaran, ['Waktu', 'Pengguna', 'Peran', 'Aksi', 'Objek', 'ID Objek', 'Alamat IP', 'Metadata']);

            AuditLog::with('user')->orderBy('created_at')->chunk(500, function ($baris) use ($keluaran) {
                foreach ($baris as $entri) {
                    fputcsv($keluaran, [
                        $entri->created_at?->format('Y-m-d H:i:s'),
                        $entri->user?->name ?? '-',
                        $entri->user?->role ?? '-',
                        $entri->action,
                        $entri->subject_type ?? '-',
                        $entri->subject_id ?? '-',
                        $entri->ip_address ?? '-',
                        json_encode($entri->metadata, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** F-1.5: riwayat login. */
    public function riwayatLogin(): View
    {
        return view('admin.riwayat-login', [
            'riwayat' => LoginHistory::with('user')->latest('created_at')->paginate(50),
        ]);
    }
}
