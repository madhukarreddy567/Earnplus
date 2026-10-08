<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CoinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    /**
     * Users' wallets overview (searchable).
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $wallets = Wallet::with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('coins')
            ->paginate(20)
            ->withQueryString();

        $totals = [
            'users' => Wallet::count(),
            'coins' => (int) Wallet::sum('coins'),
            'lifetime' => (int) Wallet::sum('lifetime_earned'),
        ];

        return view('admin.wallets.index', [
            'wallets' => $wallets,
            'totals' => $totals,
            'search' => $search,
        ]);
    }

    /**
     * One user's wallet + full transaction log with filters.
     */
    public function show(Request $request, User $user): View
    {
        $request->validate([
            'type' => ['nullable', 'in:credit,debit'],
            'source' => ['nullable', 'in:' . implode(',', CoinTransaction::SOURCES)],
        ]);

        $transactions = CoinTransaction::where('user_id', $user->id)
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('source'), fn ($q, $source) => $q->where('source', $source))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.wallets.show', [
            'user' => $user->loadMissing('wallet'),
            'transactions' => $transactions,
            'filters' => $request->only(['type', 'source']),
        ]);
    }

    /**
     * Manual coin adjustment. A reason is mandatory and the whole
     * thing is logged as an admin_adjust ledger row with the admin's id.
     */
    public function adjust(Request $request, User $user, CoinService $coins): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $admin = $request->user('admin');
        $idempotencyKey = 'admin_adjust:' . $user->id . ':' . $admin->id . ':' . now()->format('YmdHis') . ':' . \Illuminate\Support\Str::random(8);

        $meta = [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'reason' => $validated['reason'],
        ];

        try {
            if ($validated['direction'] === 'credit') {
                $coins->credit($user, $validated['amount'], CoinTransaction::SOURCE_ADMIN_ADJUST, $idempotencyKey, (string) $admin->id, $meta);
            } else {
                $coins->debit($user, $validated['amount'], CoinTransaction::SOURCE_ADMIN_ADJUST, $idempotencyKey, (string) $admin->id, $meta);
            }
        } catch (\App\Services\InsufficientBalanceException $e) {
            return redirect()
                ->route('admin.wallets.show', $user)
                ->withErrors(['amount' => 'Insufficient balance for this debit.']);
        }

        return redirect()
            ->route('admin.wallets.show', $user)
            ->with('status', "Wallet adjusted: {$validated['direction']} {$validated['amount']} coins.");
    }
}
