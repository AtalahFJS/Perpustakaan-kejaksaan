<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * F-8.4: visibilitas dokumen mengikuti klasifikasi, visibilitas kategori,
 * dan peran pengguna. Sejak pembaruan 21 Agustus 2026, kewenangan
 * mengelola konten (dulu milik Pustakawan) sepenuhnya berada di Admin;
 * Pegawai kini setara pembaca dengan akses tambahan ke konten internal.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Document $document): bool
    {
        return $document->bolehDilihatOleh($user);
    }

    /** F-4.1: membaca isi dokumen di peramban. */
    public function read(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * F-4.7: unduh berkas asli hanya untuk dokumen publik/internal.
     * Dokumen terbatas hanya boleh dibaca di peramban, tidak diunduh --
     * kecuali oleh admin yang mengelolanya.
     */
    public function download(User $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        return true;
    }

    /** F-6.1: bertanya ke dokumen -- hanya untuk dokumen yang sudah siap. */
    public function chat(User $user, Document $document): bool
    {
        return $this->view($user, $document) && $document->siapDitanya();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }

    /** F-2.7: soft delete oleh admin. */
    public function delete(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }

    /** F-2.7: hanya admin yang dapat menghapus permanen. */
    public function forceDelete(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }
}
