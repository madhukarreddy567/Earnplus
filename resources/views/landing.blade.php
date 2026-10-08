@extends('layouts.app')

@section('content')
    {{-- HERO --}}
    <section class="ep-landing-hero">
        <div class="position-relative" style="z-index:1">
            <span class="badge rounded-pill bg-white text-dark mb-3" data-animate>💰 Rewards app</span>
            <h1 class="fw-bold mb-3" style="font-size:2.1rem;line-height:1.15;" data-animate data-delay="120">
                Turn your free time into <span style="color:#ffe9a8;">coins</span>.
            </h1>
            <p class="mb-4" style="opacity:.85;" data-animate data-delay="220">
                {{ setting('site_tagline', 'Earn coins. Redeem rewards.') }}
                Tasks, spins and referrals — {{ setting_int('coins_per_rupee', 100) }} coins = ₹1.
            </p>

            {{-- Phone mockup --}}
            <div class="ep-phone mb-4" data-animate="pop" data-delay="300">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted small">Wallet balance</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ep-coin" style="width:38px;height:38px;" data-float>₹</span>
                            <span class="fw-bold" style="font-size:1.9rem;" data-countup="1250">0</span>
                        </div>
                        <div class="text-muted small">≈ ₹12.50</div>
                    </div>
                    <span class="badge rounded-pill" style="background:var(--ep-primary-soft);color:var(--ep-primary-dark);">+35 today</span>
                </div>
                <div class="ep-qa-grid" style="grid-template-columns:repeat(4,1fr);">
                    <div class="text-center"><div class="ep-qa-icon mx-auto" style="width:44px;height:44px;font-size:1.2rem;">🎯</div><div class="small fw-semibold">Tasks</div></div>
                    <div class="text-center"><div class="ep-qa-icon mx-auto" style="width:44px;height:44px;font-size:1.2rem;background:#fff4d6;">🎡</div><div class="small fw-semibold">Spin</div></div>
                    <div class="text-center"><div class="ep-qa-icon mx-auto" style="width:44px;height:44px;font-size:1.2rem;background:#e7f0fe;">📅</div><div class="small fw-semibold">Check-in</div></div>
                    <div class="text-center"><div class="ep-qa-icon mx-auto" style="width:44px;height:44px;font-size:1.2rem;background:#fdeaea;">🎁</div><div class="small fw-semibold">Invite</div></div>
                </div>
            </div>

            <div class="d-grid gap-2" data-animate data-delay="380">
                @if (google_auth_enabled())
                    <a href="{{ route('auth.google') }}" class="btn btn-light btn-lg fw-bold py-2">Start earning — it's free</a>
                @elseif (setting_bool('email_auth_enabled', false))
                    <a href="{{ route('register') }}" class="btn btn-light btn-lg fw-bold py-2">Start earning — it's free</a>
                @endif
                <a href="#how" class="btn btn-outline-light btn-lg">How it works</a>
            </div>

            <div class="ep-stat-row mt-4" data-animate data-delay="460">
                <div class="ep-stat-chip">
                    <div class="ep-stat-num" data-countup="{{ setting_int('coins_per_rupee', 100) }}">0</div>
                    <small>coins = ₹1</small>
                </div>
                <div class="ep-stat-chip">
                    <div class="ep-stat-num" data-countup="{{ setting_int('signup_bonus_coins', 50) }}">0</div>
                    <small>signup bonus</small>
                </div>
                <div class="ep-stat-chip">
                    <div class="ep-stat-num" data-countup="{{ setting_int('daily_checkin_coins', 10) }}">0</div>
                    <small>daily check-in</small>
                </div>
            </div>
        </div>
    </section>

    {{-- Site banner (DB image when the admin uploaded one) --}}
    @php($epLandingBanner = branding_url('branding_banner_1200') ?: branding_url('branding_banner'))
    @if ($epLandingBanner)
        <section class="px-3 pt-2" data-animate>
            <img src="{{ $epLandingBanner }}" alt="{{ setting('banner_title', '') }}" class="rounded-4 w-100"
                 style="max-height:220px;object-fit:cover;display:block;"
                 srcset="{{ branding_url('branding_banner_768') }} 768w, {{ $epLandingBanner }} 1200w" sizes="(max-width: 768px) 100vw, 1200px">
        </section>
    @endif

    {{-- FEATURES --}}
    <section id="features" class="px-3 py-4">
        <h2 class="fw-bold mb-1" data-animate>Everything earns</h2>
        <p class="text-muted mb-3" data-animate data-delay="100">Pick a task, earn coins, withdraw real money.</p>
        <div class="row g-3">
            @foreach ([
                ['📱', 'App installs & offers', 'Try apps and complete offers from top networks. Coins credit automatically.'],
                ['📝', 'Surveys', 'Share your opinion in short surveys and stack coins through the day.'],
                ['🎡', 'Daily spin & check-in', 'Spin the wheel and check in every day for guaranteed bonus coins.'],
                ['🤝', 'Referrals', 'Invite friends and earn a bonus for every friend who joins.'],
                ['🎬', 'Rewarded ads', 'Watch short ads and get coins instantly — verified server-side.'],
                ['💸', 'Fast withdrawals', 'Cash out to UPI, Paytm, bank, or PayPal. 100 coins = ₹1.'],
            ] as $i => $f)
                <div class="col-6" data-animate data-delay="{{ ($i % 2) * 100 }}">
                    <div class="ep-feature">
                        <div class="ep-feature-icon">{{ $f[0] }}</div>
                        <h3 class="h6 fw-bold mb-1">{{ $f[1] }}</h3>
                        <p class="text-muted small mb-0">{{ $f[2] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section id="how" class="px-3 py-4">
        <div class="ep-card" data-animate>
            <h2 class="fw-bold mb-2">How it works</h2>
            <div class="ep-step">
                <span class="ep-step-num">1</span>
                <p class="mb-0 pt-2">Sign up free with Google and grab your <strong>{{ setting_int('signup_bonus_coins', 50) }}-coin</strong> welcome bonus.</p>
            </div>
            <div class="ep-step">
                <span class="ep-step-num">2</span>
                <p class="mb-0 pt-2">Complete tasks, surveys, and daily spins to stack coins.</p>
            </div>
            <div class="ep-step">
                <span class="ep-step-num">3</span>
                <p class="mb-0 pt-2">Withdraw to UPI or PayPal once you hit the minimum.</p>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="px-3 py-4 text-center">
        <h2 class="fw-bold mb-2" data-animate>Ready to earn your first coins?</h2>
        <p class="text-muted mb-3" data-animate data-delay="100">Free to join. No investment. Just your time.</p>
        @if (google_auth_enabled())
            <a href="{{ route('auth.google') }}" class="btn btn-primary btn-lg px-5 py-2" data-animate data-delay="200">Create free account</a>
        @elseif (setting_bool('email_auth_enabled', false))
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-5 py-2" data-animate data-delay="200">Create free account</a>
        @endif
    </section>
@endsection
