<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\View\View;

/**
 * Pembaca PDF untuk pengunjung tanpa login -- hanya dokumen publik
 * (pembaruan 21 Agustus 2026). Tidak ada penanda halaman (F-4.5) di
 * sini karena fitur itu bersifat personal per akun.
 */
class PublikPembacaController extends Controller
{
    public function show(Document $document): View
    {
        abort_unless($document->bolehDilihatOleh(null), 404);

        return view('publik.pembaca.show', [
            'dokumen' => $document,
        ]);
    }
}
