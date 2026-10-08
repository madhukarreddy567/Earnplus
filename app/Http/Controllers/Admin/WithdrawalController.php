<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function __construct(protected WithdrawalService $withdrawals)
    {
    }

    /**
     * Pending queue + full log with status/method filters.
     */
    public function index(Request $request): View
    {
        $status = $request->input('status', '');
        $methodId = $request->input('method_id', '');

        $query = Withdrawal::with(['user', 'method'])->latest();

        if ($status !== '' && in_array($status, Withdrawal::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($methodId !== '') {
            $query->where('method_id', (int) $methodId);
        }

        $pending = Withdrawal::with(['user', 'method'])
            ->where('status', Withdrawal::STATUS_PENDING)
            ->latest()->limit(50)->get();

        return view('admin.withdrawals.index', [
            'pending' => $pending,
            'pendingCount' => Withdrawal::where('status', Withdrawal::STATUS_PENDING)->count(),
            'withdrawals' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'methodId' => $methodId,
            'methods' => WithdrawalMethod::ordered()->get(),
            'statuses' => Withdrawal::STATUSES,
        ]);
    }

    /**
     * Single withdrawal detail with approve/reject actions.
     */
    public function show(Withdrawal $withdrawal): View
    {
        $withdrawal->load(['user', 'method']);

        return view('admin.withdrawals.show', ['withdrawal' => $withdrawal]);
    }

    /**
     * Approve: attempt the payout driver, move to processing/completed.
     */
    public function approve(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);

        $result = $this->withdrawals->approve(
            $withdrawal,
            $request->user('admin'),
            $request->input('admin_note')
        );

        $message = "Withdrawal #{$withdrawal->id}: {$result['status']}.";
        if (! ($result['driver_live'] ?? true) && ! empty($result['driver_message'])) {
            $message .= ' ' . $result['driver_message'];
        }

        return redirect()->route('admin.withdrawals.show', $withdrawal)
            ->with('success', $message);
    }

    /**
     * Reject: mark rejected and refund the exact coins debited.
     * Idempotent — a second reject never double-refunds.
     */
    public function reject(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $result = $this->withdrawals->reject(
            $withdrawal,
            $request->user('admin'),
            $request->input('reason')
        );

        return redirect()->route('admin.withdrawals.show', $withdrawal)
            ->with('success', "Withdrawal #{$withdrawal->id}: {$result['status']}.");
    }

    // ------------------------------------------------------------------
    // Payout methods
    // ------------------------------------------------------------------

    public function methods(): View
    {
        return view('admin.withdrawal_methods.index', [
            'methods' => WithdrawalMethod::ordered()->get(),
        ]);
    }

    public function editMethod(WithdrawalMethod $method): View
    {
        return view('admin.withdrawal_methods.edit', ['method' => $method]);
    }

    public function updateMethod(Request $request, WithdrawalMethod $method): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'enabled' => ['sometimes', 'boolean'],
            'min_amount' => ['required', 'integer', 'min:1'],
            'max_amount' => ['required', 'integer', 'min:1', 'gte:min_amount'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'config' => ['nullable', 'string'],
        ]);

        $config = null;
        if (($data['config'] ?? '') !== '') {
            $decoded = json_decode($data['config'], true);
            if (! is_array($decoded)) {
                return back()->withInput()->with('error', 'Config must be valid JSON.');
            }
            $config = $decoded;
        }

        $method->update([
            'name' => $data['name'],
            'enabled' => (bool) ($data['enabled'] ?? false),
            'min_amount' => $data['min_amount'],
            'max_amount' => $data['max_amount'],
            'sort_order' => $data['sort_order'],
            'config' => $config,
        ]);

        return redirect()->route('admin.withdrawals.methods')
            ->with('success', "Method {$method->name} updated.");
    }
}
