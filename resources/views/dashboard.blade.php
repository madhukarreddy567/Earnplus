<?php $title = 'Dashboard'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 pt-3">
    @if (session('status'))
        <div class="alert alert-success border-0 rounded-4" data-animate>
            {{ session('status') }}
        </div>
    @endif

    {{-- Balance hero --}}
    <div class="ep-hero-card mb-3" data-animate>
        <div class="ep-hero-label">Total balance</div>
        <div class="d-flex align-items-center gap-3 mt-1">
            <span class="ep-coin" style="width:52px;height:52px;font-size:1.5rem;" data-float>₹</span>
            <div>
                <div class="ep-hero-balance"><span data-countup="{{ $balance }}">0</span></div>
                <div class="ep-hero-sub">≈ <strong>{{ format_rupees($rupees) }}</strong></div>
            </div>
        </div>
        <div class="ep-hero-chips">
            <span class="ep-chip">+{{ $earnedToday }} earned today</span>
            <span class="ep-chip">{{ $streak }}🔥 day streak</span>
        </div>
    </div>

    {{-- Festival promotion banner (live countdown) --}}
    <x-promo-banner :promotion="$promoBanner" />

    {{-- Quick actions --}}
    <div class="ep-qa-grid mb-3" data-animate data-delay="100">
        <a class="ep-qa" href="{{ route('tasks') }}">
            @if ($promoBadges['tasks'])
                <span class="ep-qa-badge ep-qa-badge-promo">{{ $promoBadges['tasks'] }}</span>
            @endif
            <div class="ep-qa-icon">🎯</div>
            <div class="ep-qa-label">Tasks</div>
        </a>
        <a class="ep-qa" href="{{ route('spin') }}">
            @if ($promoBadges['spin'])
                <span class="ep-qa-badge ep-qa-badge-promo">{{ $promoBadges['spin'] }}</span>
            @endif
            <div class="ep-qa-icon">🎡</div>
            <div class="ep-qa-label">Spin</div>
        </a>
        @if ($checkedInToday)
            <span class="ep-qa">
                <span class="ep-qa-badge">Done</span>
                <div class="ep-qa-icon">📅</div>
                <div class="ep-qa-label">Check-in</div>
            </span>
        @else
            <a class="ep-qa" href="{{ route('dashboard') }}#checkin">
                @if ($promoBadges['checkin'])
                    <span class="ep-qa-badge ep-qa-badge-promo">{{ $promoBadges['checkin'] }}</span>
                @endif
                <div class="ep-qa-icon">📅</div>
                <div class="ep-qa-label">Check-in</div>
            </a>
        @endif
        <a class="ep-qa" href="{{ route('tasks') }}#bonus-ads">
            @if ($promoBadges['watch_ad'])
                <span class="ep-qa-badge ep-qa-badge-promo">{{ $promoBadges['watch_ad'] }}</span>
            @endif
            <div class="ep-qa-icon">🎬</div>
            <div class="ep-qa-label">Watch Ad</div>
        </a>
        <a class="ep-qa" href="{{ route('dashboard') }}#refer">
            @if ($promoBadges['refer'])
                <span class="ep-qa-badge ep-qa-badge-promo">{{ $promoBadges['refer'] }}</span>
            @endif
            <div class="ep-qa-icon">🎁</div>
            <div class="ep-qa-label">Refer</div>
        </a>
        <a class="ep-qa" href="{{ route('withdraw.index') }}">
            <div class="ep-qa-icon">💸</div>
            <div class="ep-qa-label">Withdraw</div>
            <div class="text-muted small">₹{{ number_format($withdrawablePaise / 100, 2) }}</div>
        </a>
    </div>

    {{-- Daily check-in card (anchor target for the quick action) --}}
    <div id="checkin" class="ep-card mb-3" data-animate data-delay="140">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
                <div class="fw-bold">📅 Daily check-in</div>
                @if ($checkedInToday)
                    <div class="text-muted small">Done for today — {{ $streak }}-day streak. See you tomorrow!</div>
                @else
                    <div class="text-muted small">Claim <strong>{{ setting_int('daily_checkin_coins', 10) }} coins</strong> now @if ($streak > 0) and keep your <strong>{{ $streak }}-day</strong> streak alive @else and start a streak @endif.</div>
                @endif
            </div>
            @if ($checkedInToday)
                <button class="btn btn-success rounded-pill px-4" disabled>✓ Done</button>
            @else
                <form method="POST" action="{{ route('checkin') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Claim</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Promo banner (DB image when uploaded, gradient fallback) --}}
    @if ($bannerEnabled)
        @php($epBannerImg = branding_url('branding_banner_1200') ?: branding_url('branding_banner'))
        @if ($epBannerImg)
            <div class="ep-card mb-3 p-0 overflow-hidden border-0" data-animate data-delay="160" style="position:relative;min-height:120px;">
                <img src="{{ $epBannerImg }}" alt="" class="w-100" style="height:150px;object-fit:cover;display:block;"
                     srcset="{{ branding_url('branding_banner_768') }} 768w, {{ $epBannerImg }} 1200w" sizes="(max-width: 768px) 100vw, 1200px">
                <div class="p-3" style="position:absolute;left:0;right:0;bottom:0;background:linear-gradient(180deg,transparent,rgba(0,0,0,.65));color:#fff;">
                    <div class="fw-bold">{{ setting('banner_title', 'Invite friends, earn together!') }}</div>
                    <div class="small" style="opacity:.85;">{{ setting('banner_subtitle', 'Share your referral code — you both earn bonus coins.') }}</div>
                </div>
            </div>
        @else
        <div class="ep-card mb-3" style="background:linear-gradient(135deg,#7c3aed,#2563eb);color:#fff;border:none;" data-animate data-delay="160">
            <div class="d-flex align-items-center gap-3">
                <div class="flex-grow-1">
                    <div class="fw-bold">{{ setting('banner_title', 'Invite friends, earn together!') }}</div>
                    <div class="small" style="opacity:.8;">{{ setting('banner_subtitle', 'Share your referral code — you both earn bonus coins.') }}</div>
                </div>
                <span style="font-size:2.2rem;" data-float>🪙</span>
            </div>
        </div>
        @endif
    @endif

    {{-- Dashboard banner ad slot (renders only when a placement is eligible) --}}
    <div class="mb-3" data-animate data-delay="180">
        <x-ad placement="dashboard-banner" />
    </div>

    {{-- Referral --}}
    <div id="refer" class="ep-card mb-3" data-animate data-delay="200">
        <div class="ep-section-title"><span>🎁 Refer &amp; earn</span></div>
        <p class="text-muted small mb-3">Friends who join with your code earn you <strong>{{ setting_int('referral_bonus_coins', 25) }} coins</strong> each. You've invited <strong>{{ $referralCount }}</strong> so far.</p>
        <div class="ep-refcode mb-2">
            <code id="referralCode">{{ auth()->user()->referral_code }}</code>
            <button type="button" class="btn btn-dark btn-sm rounded-pill px-3" id="copyReferral">Copy</button>
        </div>
        <p class="text-muted small mb-0">Share link: <span class="text-break">{{ setting_bool('email_auth_enabled', false) ? route('register', ['ref' => auth()->user()->referral_code]) : route('auth.google', ['ref' => auth()->user()->referral_code]) }}</span></p>
        @if ($referrals->isNotEmpty())
            <div class="mt-3">
                @foreach ($referrals as $ref)
                    <div class="ep-tx">
                        <div class="ep-tx-icon">👤</div>
                        <div class="ep-tx-meta">
                            <div class="ep-tx-title">{{ $ref->name }}</div>
                            <div class="ep-tx-time">joined</div>
                        </div>
                        <div class="ep-tx-amount credit">+{{ setting_int('referral_bonus_coins', 25) }} 🪙</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Recent activity --}}
    <div class="ep-card mb-3" data-animate data-delay="240">
        <div class="ep-section-title"><span>🕘 Recent activity</span></div>
        @if ($history->isEmpty())
            <div class="ep-empty">Nothing here yet — check in or spin to earn your first coins.</div>
        @else
            @foreach ($history as $tx)
                <div class="ep-tx">
                    <div class="ep-tx-icon {{ $tx->isCredit() ? '' : 'debit' }}">{{ $tx->isCredit() ? '🪙' : '💸' }}</div>
                    <div class="ep-tx-meta">
                        <div class="ep-tx-title">{{ \App\Models\CoinTransaction::sourceLabel($tx->source) }}</div>
                        <div class="ep-tx-time">{{ $tx->created_at->diffForHumans() }}</div>
                    </div>
                    <div class="ep-tx-amount {{ $tx->isCredit() ? 'credit' : 'debit' }}">{{ $tx->isCredit() ? '+' : '−' }}{{ $tx->amount }}</div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ csp_nonce() }}">
(function () {
    var btn = document.getElementById('copyReferral');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var code = document.getElementById('referralCode').textContent.trim();
        var done = function () {
            btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(done, done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = code;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            done();
        }
    });
})();
</script>
@endpush
