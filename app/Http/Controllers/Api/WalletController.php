<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wallet balance + ledger history for the mobile app.
 */
class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('wallet');
        $balance = $user->coinBalance();

        return response()->json([
            'coins' => $balance,
            'rupees' => coins_to_rupees($balance),
            'lifetime_earned' => (int) ($user->wallet->lifetime_earned ?? 0),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = CoinTransaction::where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $transactions->getCollection()->map(fn (CoinTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'source' => $t->source,
                'balance_after' => $t->balance_after,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
        ]);
    }
}
