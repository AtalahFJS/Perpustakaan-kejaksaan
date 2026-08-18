@extends('layouts.app')
@section('judul', 'Kelola Pengguna')

@section('konten')
    <h1 class="mb-6 text-2xl font-semibold text-slate-900">Kelola Pengguna</h1>

    {{-- F-1.4: admin menambah pengguna --}}
    <details class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-50">
            + Tambah pengguna baru
        </summary>
        <form method="POST" action="{{ route('admin.pengguna.simpan') }}" class="border-t border-slate-100 p-5">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nama lengkap</label>
                    <input name="name" type="text" required value="{{ old('name') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nama pengguna</label>
                    <input name="username" type="text" required value="{{ old('username') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email</label>
                    <input name="email" type="email" required value="{{ old('email') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Kata sandi awal</label>
                    <input name="password" type="password" required minlength="8"
                           class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Peran</label>
                    <select name="role" required
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-korps-600 focus:ring-korps-600 sm:text-sm">
                        @foreach (\App\Models\User::SEMUA_ROLE as $kode => $label)
                            <option value="{{ $kode }}" @selected(old('role') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button class="mt-4 rounded-lg bg-korps-700 px-4 py-2 text-sm font-semibold text-white hover:bg-korps-800">
                Simpan pengguna
            </button>
        </form>
    </details>

    <form method="GET" class="mb-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau email…"
               class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
    </form>

    <div class="space-y-3">
        @foreach ($pengguna as $orang)
            <form method="POST" action="{{ route('admin.pengguna.ubah', $orang) }}"
                  class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                @csrf @method('PUT')
                <div class="grid items-end gap-3 lg:grid-cols-[2fr_2fr_1fr_1fr_auto]">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Nama</label>
                        <input name="name" type="text" required value="{{ $orang->name }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                        <p class="mt-1 text-xs text-slate-400">@ {{ $orang->username }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Email</label>
                        <input name="email" type="email" required value="{{ $orang->email }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                        <p class="mt-1 text-xs text-slate-400">
                            Login terakhir: {{ $orang->last_login_at?->format('d/m/Y H:i') ?? 'belum pernah' }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Peran</label>
                        <select name="role"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                            @foreach (\App\Models\User::SEMUA_ROLE as $kode => $label)
                                <option value="{{ $kode }}" @selected($orang->role === $kode)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Status</label>
                        <select name="is_active"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                            <option value="1" @selected($orang->is_active)>Aktif</option>
                            <option value="0" @selected(! $orang->is_active)>Nonaktif</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <input name="password" type="password" minlength="8" placeholder="Sandi baru (opsional)"
                               class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-korps-600 focus:ring-korps-600">
                        <button class="rounded-lg bg-slate-800 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-900">
                            Simpan
                        </button>
                    </div>
                </div>
            </form>
        @endforeach
    </div>

    <div class="mt-4">{{ $pengguna->links() }}</div>
@endsection
