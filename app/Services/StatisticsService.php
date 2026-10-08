<?php

namespace App\Services;

use App\Models\AdImpression;
use App\Models\CoinTransaction;
use App\Models\OfferwallConversion;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * All admin statistics in one place: users, coins, revenue,
 * ad impressions and the pending-withdrawal queue.
 *
 * Every number is computed from the live tables — nothing is cached,
 * so the admin always sees the current state.
 */
class StatisticsService
{
    /**
     * Registrations by day (last 30 days), plus weekly/monthly totals,
     * active users and referral counts.
     */
    public function userStats(): array
    {
        $daily = User::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Fill gaps so the chart has a bar for every day.
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $series[$day] = $daily[$day] ?? 0;
        }

        return [
            'total' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'today' => User::whereDate('created_at', today())->count(),
            'this_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            // Active = earned or spent coins in the last 7 days.
            'active_7d' => User::whereHas('coinTransactions', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))->count(),
            'referrals' => User::whereNotNull('referred_by')->count(),
            'daily' => $series,
        ];
    }

    /**
     * Coin economy: credits vs debits by source, plus outstanding balances.
     */
    public function coinStats(): array
    {
        $bySource = CoinTransaction::query()
            ->selectRaw('source, type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('source', 'type')
            ->get()
            ->map(fn ($row) => ['source' => $row->source, 'type' => $row->type, 'total' => (int) $row->total]);

        $credits = $bySource->where('type', CoinTransaction::TYPE_CREDIT);
        $debits = $bySource->where('type', CoinTransaction::TYPE_DEBIT);

        return [
            'total_credits' => $credits->sum('total'),
            'total_debits' => $debits->sum('total'),
            'credits_by_source' => $credits->pluck('total', 'source')->map(fn ($v) => (int) $v)->all(),
            'debits_by_source' => $debits->pluck('total', 'source')->map(fn ($v) => (int) $v)->all(),
            'outstanding_coins' => (int) Wallet::sum('coins'),
            'lifetime_earned' => (int) Wallet::sum('lifetime_earned'),
            'wallets' => Wallet::count(),
        ];
    }

    /**
     * Revenue: offerwall earnings by provider, ad activity, payout
     * outflows and the net margin.
     *
     * Coins are converted to rupees with the admin-editable
     * coins_per_rupee setting so the numbers track the real rate.
     */
    public function revenueStats(): array
    {
        $perRupee = max(1, setting_int('coins_per_rupee', 100));

        $offerwallByProvider = OfferwallConversion::query()
            ->selectRaw('provider_id, COALESCE(SUM(payout_coins), 0) as coins, COUNT(*) as conversions')
            ->where('status', OfferwallConversion::STATUS_CREDITED)
            ->groupBy('provider_id')
            ->with('provider:id,slug,name')
            ->get()
            ->map(fn ($row) => [
                'provider' => $row->provider?->name ?? $row->provider?->slug ?? '—',
                'conversions' => (int) $row->conversions,
                'coins' => (int) $row->coins,
                'rupees' => round(((int) $row->coins) / $perRupee, 2),
            ]);

        $offerwallCoins = OfferwallConversion::where('status', OfferwallConversion::STATUS_CREDITED)->sum('payout_coins');

        // Payout outflows: approved / processing / completed withdrawals.
        $paidOut = Withdrawal::query()
            ->whereIn('status', [
                Withdrawal::STATUS_APPROVED,
                Withdrawal::STATUS_PROCESSING,
                Withdrawal::STATUS_COMPLETED,
            ])
            ->selectRaw('COALESCE(SUM(net_paise), 0) as paise, COUNT(*) as total')
            ->first();

        $pendingOutflow = Withdrawal::query()
            ->where('status', Withdrawal::STATUS_PENDING)
            ->selectRaw('COALESCE(SUM(net_paise), 0) as paise, COUNT(*) as total')
            ->first();

        $offerwallRupees = $offerwallCoins / $perRupee;
        $paidRupees = ((int) $paidOut->paise) / 100;

        return [
            'offerwall_by_provider' => $offerwallByProvider->values()->all(),
            'offerwall_coins' => (int) $offerwallCoins,
            'offerwall_rupees' => round($offerwallRupees, 2),
            'paid_out_rupees' => round($paidRupees, 2),
            'paid_out_count' => (int) $paidOut->total,
            'pending_outflow_rupees' => round(((int) $pendingOutflow->paise) / 100, 2),
            'pending_outflow_count' => (int) $pendingOutflow->total,
            'net_margin_rupees' => round($offerwallRupees - $paidRupees, 2),
        ];
    }

    /**
     * Ad impressions by placement, network and device.
     */
    public function impressionStats(): array
    {
        $total = AdImpression::count();

        $byPlacement = AdImpression::query()
            ->selectRaw('placement_id, COUNT(*) as total')
            ->groupBy('placement_id')
            ->with('placement:id,name,slot')
            ->get()
            ->map(fn ($row) => [
                'placement' => $row->placement?->name ?? '—',
                'slot' => $row->placement?->slot ?? '',
                'total' => (int) $row->total,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $byNetwork = AdImpression::query()
            ->join('ad_placements', 'ad_placements.id', '=', 'ad_impressions.placement_id')
            ->join('ad_networks', 'ad_networks.id', '=', 'ad_placements.network_id')
            ->selectRaw('ad_networks.name as network, COUNT(*) as total')
            ->groupBy('ad_networks.name')
            ->orderByDesc('total')
            ->pluck('total', 'network')
            ->map(fn ($v) => (int) $v)
            ->all();

        $byDevice = AdImpression::query()
            ->selectRaw('COALESCE(device, ?) as device, COUNT(*) as total', ['unknown'])
            ->groupBy('device')
            ->orderByDesc('total')
            ->pluck('total', 'device')
            ->map(fn ($v) => (int) $v)
            ->all();

        $rewarded = AdImpression::where('rewarded', true)->count();

        return [
            'total' => $total,
            'rewarded' => $rewarded,
            'by_placement' => $byPlacement,
            'by_network' => $byNetwork,
            'by_device' => $byDevice,
        ];
    }

    /**
     * The live pending-withdrawal queue for the dashboard widget.
     */
    public function pendingWithdrawals(int $limit = 8): array
    {
        $items = Withdrawal::query()
            ->where('status', Withdrawal::STATUS_PENDING)
            ->with(['user:id,name,email', 'method:id,name'])
            ->latest('requested_at')
            ->limit($limit)
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'user' => $w->user?->name ?? $w->user?->email ?? '—',
                'method' => $w->method?->name ?? '—',
                'coins' => (int) $w->coins_debited,
                'rupees' => round(((int) $w->net_paise) / 100, 2),
                'requested_at' => $w->requested_at?->diffForHumans(),
                'review_url' => route('admin.withdrawals.show', $w),
            ]);

        return [
            'count' => Withdrawal::where('status', Withdrawal::STATUS_PENDING)->count(),
            'items' => $items->values()->all(),
            'queue_url' => route('admin.withdrawals.index'),
        ];
    }

    /**
     * One call that powers the whole dashboard.
     */
    public function overview(): array
    {
        return [
            'users' => $this->userStats(),
            'coins' => $this->coinStats(),
            'revenue' => $this->revenueStats(),
            'impressions' => $this->impressionStats(),
            'pending_withdrawals' => $this->pendingWithdrawals(),
        ];
    }
}
