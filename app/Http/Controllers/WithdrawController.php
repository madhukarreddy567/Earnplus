<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawController extends Controller
{
    public function __construct(protected WithdrawalService $withdrawals)
    {
    }

    /**
     * The withdraw page: method picker, presets + custom amount,
     * live preview, payout details, recent requests.
     */
    public function index(): View
    {
        $user = auth()->user();
        $user->loadMissing('wallet');

        return view('withdraw.index', [
            'methods' => WithdrawalMethod::enabled()->ordered()->get(),
            'balance' => $user->coinBalance(),
            'withdrawablePaise' => $this->withdrawals->coinsToPaise($user->coinBalance()),
            'withdrawalsEnabled' => setting_bool('withdrawals_enabled', true),
            'maxPerDay' => setting_int('withdrawal_max_per_day', 3),
            'requestsToday' => $this->withdrawals->requestsToday($user),
            'presets' => [1000, 2000, 3000], // paise: ₹10 / ₹20 / ₹30
            'idempotencyKey' => WithdrawalService::newIdempotencyKey(),
            'history' => Withdrawal::where('user_id', $user->id)
                ->with('method')->latest()->limit(10)->get(),
        ]);
    }

    /**
     * Server-computed quote for the live preview (never trust client math).
     */
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
            'amount' => $method->formatAmount($quote['amount_paise']),
            'tax' => '₹' . number_format($quote['tax_paise'] / 100, 2),
            'tax_paise' => $quote['tax_paise'],
            'net' => $method->isUsd()
                ? '$' . number_format(($quote['net_usd_cents'] ?? 0) / 100, 2)
                : '₹' . number_format($quote['net_paise'] / 100, 2),
            'within_limits' => $data['amount'] >= $method->min_amount
                && $data['amount'] <= $method->max_amount,
            'min' => $method->formatAmount($method->min_amount),
            'max' => $method->formatAmount($method->max_amount),
        ]);
    }

    /**
     * Submit a withdrawal request.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'method_id' => ['required', 'integer', 'exists:withdrawal_methods,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $method = WithdrawalMethod::findOrFail($data['method_id']);
        $details = $this->validatedDetails($request, $method);

        try {
            $withdrawal = $this->withdrawals->request(
                auth()->user(),
                $method,
                $data['amount'],
                $details,
                $data['idempotency_key']
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\App\Services\InsufficientBalanceException $e) {
            return back()->withInput()->with('error', 'Not enough coins for this withdrawal.');
        }

        return redirect()->route('withdraw.show', $withdrawal)
            ->with('success', 'Withdrawal requested — it is now pending review.');
    }

    /**
     * Single withdrawal detail (owner only).
     */
    public function show(Withdrawal $withdrawal): View
    {
        abort_unless($withdrawal->user_id === auth()->id(), 403);

        $withdrawal->load('method');

        return view('withdraw.show', ['withdrawal' => $withdrawal]);
    }

    /**
     * Validate payout details according to the chosen method.
     *
     * @return array<string, string>
     */
    protected function validatedDetails(Request $request, WithdrawalMethod $method): array
    {
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

        // Normalize IFSC to uppercase.
        if (isset($details['ifsc'])) {
            $details['ifsc'] = strtoupper($details['ifsc']);
        }

        return $details;
    }
}
