<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // --- Akun demo untuk tiap persona (PRD bagian 4) ---
        // Sejak pembaruan 21 Agustus 2026: hanya dua peran setelah login.
        // Admin mengelola konten; Pegawai adalah pembaca dengan akses
        // tambahan ke kategori internal (lihat User::ROLE_PEGAWAI).
        $akun = [
            ['Administrator Sistem', 'admin', 'admin@pustakaai.local', User::ROLE_ADMIN],
            ['Pegawai Kejaksaan', 'pegawai', 'pegawai@pustakaai.local', User::ROLE_PEGAWAI],
        ];

        foreach ($akun as [$nama, $username, $email, $peran]) {
            User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $nama,
                    'email' => $email,
                    // Kata sandi demo -- wajib diganti sebelum dipakai sungguhan.
                    'password' => 'rahasia123',
                    'role' => $peran,
                    'is_active' => true,
                ],
            );
        }

        // --- Kategori awal ---
        // 'internal' berarti hanya pengguna yang sudah masuk (Pegawai/Admin)
        // yang melihatnya di katalog; pengunjung publik tidak melihatnya
        // sama sekali (pembaruan 21 Agustus 2026).
        $kategori = [
            'Peraturan Perundang-undangan',
            'Undang-Undang',
            'Peraturan Pemerintah',
            'Peraturan Kejaksaan',
            'Pedoman & Petunjuk Teknis',
            'Pedoman Kerja',
            'Standar Operasional Prosedur',
            'Putusan & Yurisprudensi',
            'Kajian & Jurnal',
            'Jurnal Hukum',
            'Hasil Penelitian',
            'Laporan Kelembagaan',
        ];

        // Kategori kini pengelompokan murni: tanpa visibilitas, dan
        // urutan tampilnya mengikuti susunan daftar di atas.
        foreach ($kategori as $nomor => $nama) {
            Category::updateOrCreate(
                ['slug' => Str::slug($nama)],
                ['name' => $nama, 'urutan' => $nomor + 1],
            );
        }

        // --- Tag umum ---
        foreach (['tindak pidana korupsi', 'perdata', 'tata usaha negara', 'intelijen', 'pidana umum', 'kepegawaian'] as $tag) {
            Tag::updateOrCreate(['slug' => Str::slug($tag)], ['name' => $tag]);
        }
    }
}
