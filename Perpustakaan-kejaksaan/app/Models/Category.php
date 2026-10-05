<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori dokumen.
 *
 * Pembaruan 26 Agustus 2026: kategori kembali menjadi pengelompokan murni.
 * Ia tidak lagi menentukan siapa yang boleh melihat dokumen -- itu urusan
 * `Document::classification` yang dipilih per dokumen saat unggah. Kolom
 * `visibility` beserta seluruh logikanya sudah dibuang.
 *
 * Yang ditambahkan: kolom `urutan`, agar admin dapat menyusun sendiri
 * tampilan kategori di katalog.
 */
class Category extends Model
{
    protected $fillable = ['name', 'slug', 'urutan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Urutan tampil di katalog: mengikuti susunan yang ditetapkan admin,
     * lalu nama sebagai penyeimbang bila ada yang bernomor sama.
     */
    public function scopeTerurut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('name');
    }
}
