<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateTransaction;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    /**
     * Riwayat transaksi. Kasir hanya melihat transaksinya sendiri;
     * admin melihat semua. Mendukung filter rentang tanggal.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $transactions = Transaction::query()
            ->with(['user', 'items'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('dari'), fn ($q) => $q
                ->whereDate('created_at', '>=', $request->date('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q
                ->whereDate('created_at', '<=', $request->date('sampai')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return TransactionResource::collection($transactions);
    }

    /**
     * Proses checkout. Memakai action yang sama dengan halaman kasir web
     * (stok terkunci & berkurang otomatis, invoice unik, snapshot harga).
     */
    public function store(Request $request, CreateTransaction $createTransaction): TransactionResource
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'paid' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ]);

        $method = PaymentMethod::from($data['payment_method']);

        $transaction = $createTransaction->handle(
            cashier: $request->user(),
            cart: $data['items'],
            paid: (float) $data['paid'],
            method: $method,
        );

        return TransactionResource::make($transaction->load(['items', 'user']));
    }

    /** Detail satu transaksi (admin atau pemilik transaksi saja). */
    public function show(Request $request, Transaction $transaction): TransactionResource
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || $transaction->user_id === $user->id,
            403,
            'Anda tidak berhak melihat transaksi ini.',
        );

        return TransactionResource::make($transaction->load(['items', 'user']));
    }
}
