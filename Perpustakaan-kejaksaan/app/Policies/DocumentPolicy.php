<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * F-8.4: visibilitas dokumen mengikuti klasifikasi dan peran pengguna.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Document $document): bool
    {
        return in_array(
            $document->classification,
            $user->klasifikasiYangBolehDilihat(),
            true,
        );
    }

    /** F-4.1: membaca isi dokumen di peramban. */
    public function read(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * F-4.7: unduh berkas asli hanya untuk dokumen publik/internal.
     * Dokumen terbatas hanya boleh dibaca di peramban, tidak diunduh --
     * kecuali oleh pustakawan yang mengelolanya.
     */
    public function download(User $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        if ($document->classification === Document::KLASIFIKASI_TERBATAS) {
            return $user->isPustakawan();
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
        return $user->isPustakawan();
    }

    public function update(User $user, Document $document): bool
    {
        return $user->isPustakawan();
    }

    /** F-2.7: soft delete oleh pustakawan. */
    public function delete(User $user, Document $document): bool
    {
        return $user->isPustakawan();
    }

    /** F-2.7: hanya admin yang dapat menghapus permanen. */
    public function forceDelete(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->isPustakawan();
    }
}
