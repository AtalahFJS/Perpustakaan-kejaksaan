<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Summary;
use App\Services\KlienLayananAi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Peninjauan ringkasan oleh pustakawan (PRD 5.5: F-5.5, F-5.6).
 */
class RingkasanController extends Controller
{
    public function __construct(private readonly KlienLayananAi $ai) {}

    /** F-5.5: pustakawan menyunting ringkasan sebelum tampil. */
    public function update(Request $request, Summary $summary): RedirectResponse
    {
        $this->authorize('update', $summary->document);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
        ]);

        $summary->update([
            'content' => $data['content'],
            'is_approved' => true,
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('sukses', 'Ringkasan diperbarui dan disetujui.');
    }

    /** F-5.5: menyetujui atau menarik kembali ringkasan. */
    public function setujui(Request $request, Summary $summary): RedirectResponse
    {
        $this->authorize('update', $summary->document);

        $disetujui = ! $summary->is_approved;

        $summary->update([
            'is_approved' => $disetujui,
            'approved_by' => $disetujui ? $request->user()->id : null,
        ]);

        return back()->with('sukses', $disetujui
            ? 'Ringkasan disetujui dan kini tampil bagi pembaca.'
            : 'Ringkasan disembunyikan dari pembaca.');
    }

    /** F-5.6: memicu pembuatan ulang seluruh ringkasan satu dokumen. */
    public function buatUlang(Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        if (! $document->bolehDiprosesAi()) {
            return back()->withErrors(['ringkasan' => 'Dokumen terbatas tidak diproses AI.']);
        }

        if ($document->chunks()->doesntExist()) {
            return back()->withErrors(['ringkasan' => 'Dokumen belum diekstrak. Jalankan pemrosesan ulang terlebih dahulu.']);
        }

        try {
            $this->ai->buatUlangRingkasan($document->id);
        } catch (Throwable $galat) {
            return back()->withErrors(['ringkasan' => $galat->getMessage()]);
        }

        return back()->with('sukses', 'Ringkasan dibuat ulang.');
    }
}
