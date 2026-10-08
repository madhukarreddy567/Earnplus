<?php

namespace App\Http\Controllers;

use App\Models\CoinTransaction;
use App\Models\Promotion;
use App\Services\CheckinService;
use App\Services\PromotionService;
use App\Services\SpinService;
use App\Services\Withdrawals\WithdrawalService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The user's wallet dashboard: balance, rupee value, today's
     * earnings, check-in state, spin state, referral card, history.
     */
    public function index(
        CheckinService $checkin,
        SpinService $spin,
        WithdrawalService $withdrawals,
        PromotionService $promotions
    ): View {
        $user = auth()->user();
        $user->ensureReferralCode();
        $user->loadMissing('wallet');

        $balance = $user->coinBalance();
        $todayStart = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        $earnedToday = CoinTransaction::where('user_id', $user->id)
            ->where('type', CoinTransaction::TYPE_CREDIT)
            ->where('created_at', '>=', $todayStart)
            ->sum('amount');

        $history = $user->coinTransactions()->limit(10)->get();

        $referrals = $user->referrals()->latest()->limit(5)->get();

        return view('dashboard', [
            'balance' => $balance,
            'rupees' => coins_to_rupees($balance),
            'earnedToday' => (int) $earnedToday,
            'history' => $history,
            'referrals' => $referrals,
            'referralCount' => $user->referrals()->count(),
            'checkedInToday' => $checkin->checkedInToday($user),
            'streak' => $checkin->currentStreak($user),
            'spinEnabled' => $spin->enabled(),
            'spinsLeft' => $spin->spinsLeftToday($user),
            'bannerEnabled' => setting_bool('banner_enabled', true),
            'withdrawablePaise' => $withdrawals->coinsToPaise($balance),
            'promoBanner' => $promotions->bannerFor(Promotion::SCOPE_GLOBAL),
            'promoBadges' => [
                'tasks' => $promotions->badgeFor(Promotion::SCOPE_TASK_OFFERWALL),
                'spin' => $promotions->badgeFor(Promotion::SCOPE_SPIN),
                'checkin' => $promotions->badgeFor(Promotion::SCOPE_DAILY_CHECKIN),
                'watch_ad' => $promotions->badgeFor(Promotion::SCOPE_REWARDED_AD),
                'refer' => $promotions->badgeFor(Promotion::SCOPE_REFERRAL_BONUS),
            ],
        ]);
    }
}
