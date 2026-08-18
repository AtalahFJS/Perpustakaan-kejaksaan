<?php

namespace App\Policies;

use App\Models\Summary;
use App\Models\User;

/**
 * F-5.5: hanya pustakawan yang meninjau, menyunting, dan menyetujui ringkasan.
 */
class SummaryPolicy
{
    public function view(User $user, Summary $summary): bool
    {
        if (! in_array($summary->document->classification, $user->klasifikasiYangBolehDilihat(), true)) {
            return false;
        }

        // Ringkasan yang belum disetujui hanya terlihat oleh pustakawan.
        return $summary->is_approved || $user->isPustakawan();
    }

    public function update(User $user, Summary $summary): bool
    {
        return $user->isPustakawan();
    }
}
