<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Ringkasan penjualan per rentang tanggal (khusus admin). */
    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Khusus admin.');

        $dari = ($request->date('dari') ?? today()->startOfMonth())->toDateString();
        $sampai = ($request->date('sampai') ?? today())->toDateString();

        $base = fn () => Transaction::whereDate('created_at', '>=', $dari)
            ->whereDate('created_at', '<=', $sampai);

        $omzet = (float) $base()->sum('total');
        $jumlah = $base()->count();

        $top = TransactionItem::query()
            ->whereHas('transaction', fn ($q) => $q
                ->whereDate('created_at', '>=', $dari)
                ->whereDate('created_at', '<=', $sampai))
            ->selectRaw('product_name, SUM(qty) as qty_total, SUM(subtotal) as omzet_total')
            ->groupBy('product_name')
            ->orderByDesc('qty_total')
            ->limit(10)
            ->get();

        return response()->json([
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'omzet' => $omzet,
            'jumlah_transaksi' => $jumlah,
            'rata_rata' => $jumlah > 0 ? round($omzet / $jumlah, 2) : 0,
            'produk_terlaris' => $top->map(fn ($r) => [
                'product_name' => $r->product_name,
                'qty' => (int) $r->qty_total,
                'omzet' => (float) $r->omzet_total,
            ]),
        ]);
    }
}
