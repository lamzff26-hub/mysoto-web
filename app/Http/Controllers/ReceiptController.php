<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * Hasilkan struk PDF (PRD 4.7).
     *
     * Otorisasi: hanya admin atau kasir yang membuat transaksi tersebut yang
     * boleh melihat struknya.
     *
     * ?download=1 untuk mengunduh; default ditampilkan inline (bisa langsung
     * di-print dari browser).
     */
    public function show(Request $request, Transaction $transaction)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $transaction->user_id === $user->id, 403);

        $transaction->load(['items', 'user']);

        // Gunakan query parameter jika ada, kalau tidak gunakan setting default
        $size = (int) $request->query('size', 0);
        if ($size === 0) {
            $size = (int) Setting::get('receipt_size', 80);
        }
        if (! in_array($size, [58, 80], true)) {
            $size = 80;
        }

        $paperWidths = [
            58 => 164.57, // 58mm in points
            80 => 226.77, // 80mm in points
        ];

        $pdf = Pdf::loadView('receipt', [
                'trx' => $transaction,
                'pageWidthMm' => $size,
                'storeName' => Setting::get('store_name', 'MySoto'),
                'storeAddress' => Setting::get('store_address', 'Alamat toko belum disetel'),
                'storeLogo' => Setting::get('store_logo'),
            ])
            ->setPaper([0, 0, $paperWidths[$size], 650]);

        $filename = $transaction->invoice_number . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
