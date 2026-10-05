<?php

namespace App\Policies;

use App\Models\Summary;
use App\Models\User;

/**
 * F-5.5: hanya Admin yang meninjau, menyunting, dan menyetujui ringkasan
 * (kewenangan ini dulu milik Pustakawan; sejak pembaruan 21 Agustus 2026
 * sepenuhnya berpindah ke Admin).
 */
class SummaryPolicy
{
    public function view(User $user, Summary $summary): bool
    {
        if (! $summary->document->bolehDilihatOleh($user)) {
            return false;
        }

        // Ringkasan yang belum disetujui hanya terlihat oleh admin.
        return $summary->is_approved || $user->isAdmin();
    }

    public function update(User $user, Summary $summary): bool
    {
        return $user->isAdmin();
    }
}
