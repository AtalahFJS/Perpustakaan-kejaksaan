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
        $akun = [
            ['Administrator Sistem', 'admin', 'admin@pustakaai.local', User::ROLE_ADMIN],
            ['Pustakawan Utama', 'pustakawan', 'pustakawan@pustakaai.local', User::ROLE_PUSTAKAWAN],
            ['Pembaca Umum', 'pembaca', 'pembaca@pustakaai.local', User::ROLE_PEMBACA],
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
        $kategori = [
            'Peraturan Perundang-undangan' => ['Undang-Undang', 'Peraturan Pemerintah', 'Peraturan Kejaksaan'],
            'Pedoman & Petunjuk Teknis' => ['Pedoman Kerja', 'Standar Operasional Prosedur'],
            'Putusan & Yurisprudensi' => [],
            'Kajian & Jurnal' => ['Jurnal Hukum', 'Hasil Penelitian'],
            'Laporan Kelembagaan' => [],
        ];

        foreach ($kategori as $induk => $anakSemua) {
            $indukModel = Category::updateOrCreate(
                ['slug' => Str::slug($induk)],
                ['name' => $induk, 'parent_id' => null],
            );

            foreach ($anakSemua as $anak) {
                Category::updateOrCreate(
                    ['slug' => Str::slug($anak)],
                    ['name' => $anak, 'parent_id' => $indukModel->id],
                );
            }
        }

        // --- Tag umum ---
        foreach (['tindak pidana korupsi', 'perdata', 'tata usaha negara', 'intelijen', 'pidana umum', 'kepegawaian'] as $tag) {
            Tag::updateOrCreate(['slug' => Str::slug($tag)], ['name' => $tag]);
        }
    }
}
