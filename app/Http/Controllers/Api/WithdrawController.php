<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Withdrawals for the mobile app (JSON mirror of the web flow —
 * integer-paise math, idempotent requests, exact-refund rejections).
 */
class WithdrawController extends Controller
{
    public function __construct(protected WithdrawalService $withdrawals)
    {
    }

    public function methods(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('wallet');

        $methods = WithdrawalMethod::enabled()->ordered()->get();

        return response()->json([
            'enabled' => setting_bool('withdrawals_enabled', true),
            'balance_coins' => $user->coinBalance(),
            'withdrawable_paise' => $this->withdrawals->coinsToPaise($user->coinBalance()),
            'max_per_day' => setting_int('withdrawal_max_per_day', 3),
            'requests_today' => $this->withdrawals->requestsToday($user),
            'presets_paise' => [1000, 2000, 3000],
            'data' => $methods->map(fn (WithdrawalMethod $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->type,
                'currency' => $m->isUsd() ? 'USD' : 'INR',
                'min' => $m->formatAmount($m->min_amount),
                'max' => $m->formatAmount($m->max_amount),
                'min_amount' => $m->min_amount,
                'max_amount' => $m->max_amount,
                'detail_fields' => $m->detailFields(),
            ])->values(),
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method_id' => ['required', 'integer', 'exists:withdrawal_methods,id'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $method = WithdrawalMethod::enabled()->findOrFail($data['method_id']);
        $quote = $this->withdrawals->quote($method, $data['amount']);

        return response()->json([
            'coins' => $quote['coins'],
            'tax_paise' => $quote['tax_paise'],
            'net_display' => $method->isUsd()
                ? '$' . number_format(($quote['net_usd_cents'] ?? 0) / 100, 2)
                : '₹' . number_format($quote['net_paise'] / 100, 2),
            'within_limits' => $data['amount'] >= $method->min_amount
                && $data['amount'] <= $method->max_amount,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method_id' => ['required', 'integer', 'exists:withdrawal_methods,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'details' => ['required', 'array'],
        ]);

        $method = WithdrawalMethod::findOrFail($data['method_id']);

        // Validate payout details with the same rules as the web form.
        $rules = [];
        foreach ($method->detailFields() as $field) {
            $rules["details.{$field}"] = match ($field) {
                'upi_id' => ['required', 'string', 'max:256', 'regex:/^[\w.\-]{2,256}@[a-zA-Z]{2,64}$/'],
                'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
                'account_holder' => ['required', 'string', 'max:100'],
                'account_no' => ['required', 'string', 'regex:/^\d{9,18}$/'],
                'ifsc' => ['required', 'string', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
                'paypal_name' => ['required', 'string', 'max:120'],
                'paypal_email' => ['required', 'email:rfc', 'max:190'],
                default => ['required', 'string', 'max:255'],
            };
        }
        $validated = $request->validate($rules);
        $details = $validated['details'] ?? [];
        if (isset($details['ifsc'])) {
            $details['ifsc'] = strtoupper($details['ifsc']);
        }

        try {
            $withdrawal = $this->withdrawals->request(
                $request->user(),
                $method,
                $data['amount'],
                $details,
                $data['idempotency_key']
            );
        } catch (\App\Services\InsufficientBalanceException) {
            return response()->json(['message' => 'Not enough coins for this withdrawal.'], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $withdrawal->id,
            'status' => $withdrawal->status,
            'message' => 'Withdrawal requested — it is now pending review.',
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $withdrawals = Withdrawal::where('user_id', $request->user()->id)
            ->with('method')
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $withdrawals->getCollection()->map(fn (Withdrawal $w) => [
                'id' => $w->id,
                'method' => $w->method?->name,
                'amount_display' => $w->method?->formatAmount($w->amount_paise),
                'status' => $w->status,
                'created_at' => $w->created_at?->toIso8601String(),
            ])->values(),
            'current_page' => $withdrawals->currentPage(),
            'last_page' => $withdrawals->lastPage(),
        ]);
    }
}
