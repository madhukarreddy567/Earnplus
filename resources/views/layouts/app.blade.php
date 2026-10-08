<!DOCTYPE html>
@php($epTheme = setting('theme', 'light') === 'dark' ? 'dark' : 'light')
@php($epAnimations = setting_bool('animations_enabled', true))
<html lang="en" data-theme="{{ $epTheme }}" class="{{ $epAnimations ? '' : 'no-anime' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ setting('site_name', 'EarnPlus') }}{{ isset($title) ? ' — ' . $title : '' }}</title>
    @yield('head')
    @php($epFavicon32 = branding_url('branding_favicon_32'))
    @php($epAppleTouch = branding_url('branding_favicon_180'))
    @if ($epFavicon32)
        <link rel="icon" type="image/png" href="{{ $epFavicon32 }}">
        @if ($epAppleTouch)
            <link rel="apple-touch-icon" href="{{ $epAppleTouch }}">
        @endif
    @else
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🪙</text></svg>">
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @php($epPrimary = design_color('design_primary', '#0e9f6e'))
    @php($epHeroFrom = design_color('design_hero_from', '#0e9f6e'))
    @php($epHeroTo = design_color('design_hero_to', '#075e43'))
    @php($epFontScale = setting_int('font_scale', 100) / 100)
    @php($epBgUrl = branding_url('branding_background'))
    @php($epBgStyle = setting('background_style', 'cover') === 'blur' ? 'blur' : 'cover')
    <style>
        /* Phase 8 design settings — emitted from the DB, apply instantly. */
        :root {
            --ep-primary: {{ $epPrimary }};
            --ep-primary-dark: {{ shade_color($epPrimary, -14) }};
            --ep-primary-deep: {{ shade_color($epPrimary, -28) }};
            --ep-accent: {{ design_color('design_accent', '#0ea5e9') }};
            --ep-hero-from: {{ $epHeroFrom }};
            --ep-hero-to: {{ $epHeroTo }};
            --ep-font-scale: {{ $epFontScale }};
        }
        html { font-size: calc(16px * var(--ep-font-scale)); }
        .ep-landing-hero {
            background: linear-gradient(135deg, var(--ep-hero-from) 0%, var(--ep-hero-to) 100%);
        }
        body[data-btn-shape="rounded"] .btn { border-radius: 12px; }
        body[data-btn-shape="pill"] .btn { border-radius: 999px; }
        body[data-btn-shape="square"] .btn { border-radius: 4px; }
        html[data-theme="dark"] {
            --ep-bg: #0f1720;
            --ep-card: #1a2430;
            --ep-ink: #e8eef4;
            --ep-muted: #93a3b5;
            --ep-line: #2a3644;
            --ep-primary-soft: #123f31;
        }
        html[data-theme="dark"] .ep-topbar { background: rgba(26, 36, 48, 0.92); }
        html[data-theme="dark"] .ep-card { box-shadow: 0 10px 30px -12px rgba(0, 0, 0, 0.5); }
        html[data-theme="dark"] .form-control, html[data-theme="dark"] .form-select {
            background-color: #22303f;
            border-color: #2a3644;
            color: #e8eef4;
        }
        .ep-brand-logo { height: 34px; width: auto; max-width: 150px; object-fit: contain; }
        @if ($epBgUrl)
            body.ep-bg-cover {
                background-image: url('{{ $epBgUrl }}');
                background-size: cover;
                background-position: center;
                background-attachment: fixed;
            }
            body.ep-bg-blur { position: relative; }
            body.ep-bg-blur::before {
                content: "";
                position: fixed;
                inset: 0;
                background-image: url('{{ $epBgUrl }}');
                background-size: cover;
                background-position: center;
                filter: blur(22px) brightness(0.72);
                z-index: -1;
            }
        @endif
    </style>
    @stack('styles')
</head>
@php($epBtnShape = setting('button_shape', 'pill'))
@php($epBgClass = $epBgUrl ? ($epBgStyle === 'blur' ? 'ep-bg-blur' : 'ep-bg-cover') : '')
<body data-btn-shape="{{ $epBtnShape }}" class="{{ $epBgClass }}">
    @php($isAdmin = auth('admin')->check())
    @php($isUser = auth()->check())
    @php($shellClass = $isAdmin || ! empty($wide) ? 'ep-shell-wide' : 'ep-shell')
    @php($currentRoute = request()->route()?->getName())

    {{-- Phase 13: development-mode ribbon (production shows nothing). --}}
    @if (($epAppMode ?? 'development') === 'development' && ! ($isAdmin ?? false))
        <div style="position:fixed;top:0;left:0;right:0;z-index:2000;background:#f59e0b;color:#1f2937;text-align:center;font-size:11px;font-weight:700;letter-spacing:.5px;padding:3px 8px;">DEVELOPMENT MODE — license checks are lenient</div>
        <div style="height:22px;"></div>
    @endif

    <div class="{{ $shellClass }}">
        {{-- Top app bar --}}
        <header class="ep-topbar">
            <div class="ep-topbar-inner">
                @php($epLogoUrl = branding_url('branding_logo'))
                <a class="ep-brand" href="{{ $isAdmin ? route('admin.dashboard') : route('landing') }}">
                    @if ($epLogoUrl)
                        <img src="{{ $epLogoUrl }}" alt="{{ setting('site_name', 'EarnPlus') }}" class="ep-brand-logo">
                    @else
                        <span class="ep-coin" style="width:34px;height:34px;font-size:1rem;">₹</span>
                        <span>{{ setting('site_name', 'EarnPlus') }}</span>
                    @endif
                </a>

                @if ($isAdmin)
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small d-none d-sm-inline">{{ auth('admin')->user()->name }}</span>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">Admin panel</a>
                        <form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
                        </form>
                    </div>
                @elseif ($isUser)
                    <div class="d-flex align-items-center gap-2">
                        <a class="ep-balance-pill" href="{{ route('dashboard') }}">
                            <span class="ep-coin">₹</span>
                            <span data-countup="{{ auth()->user()->coinBalance() }}">0</span>
                        </a>
                        <div class="dropdown">
                            <button class="btn p-0 border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                                @if (auth()->user()->avatar)
                                    <img src="{{ auth()->user()->avatar }}" alt="" class="ep-avatar" referrerpolicy="no-referrer">
                                @else
                                    <span class="ep-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                                @endif
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><span class="dropdown-item-text fw-bold">{{ auth()->user()->name }}</span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('dashboard') }}">🏠 Dashboard</a></li>
                                <li><a class="dropdown-item" href="{{ route('tasks') }}">🎯 Tasks</a></li>
                                <li><a class="dropdown-item" href="{{ route('spin') }}">🎡 Spin wheel</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">Log out</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                @elseif (setting_bool('email_auth_enabled', false) || google_auth_enabled())
                    <a class="btn btn-primary btn-sm px-3" href="{{ setting_bool('email_auth_enabled', false) ? route('register') : route('login') }}">Get Started</a>
                @endif
            </div>
        </header>

        {{-- Page content --}}
        <main class="{{ $isUser && ! $isAdmin ? 'ep-has-bottomnav' : '' }}">
            @yield('content')
        </main>

        <footer class="ep-footer">
            <div>© {{ date('Y') }} {{ setting('site_name', 'EarnPlus') }}. {{ setting('site_tagline', 'Earn coins. Redeem rewards.') }}</div>
            <div class="mt-1">{{ setting_int('coins_per_rupee', 100) }} coins = ₹1</div>
        </footer>

        {{-- Bottom navigation (signed-in users only) --}}
        @if ($isUser && ! $isAdmin)
            <nav class="ep-bottomnav" aria-label="Primary">
                <a class="ep-nav-item {{ $currentRoute === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <span class="ep-nav-icon">🏠</span>
                    <span>Home</span>
                </a>
                <a class="ep-nav-item {{ $currentRoute === 'tasks' ? 'active' : '' }}" href="{{ route('tasks') }}">
                    <span class="ep-nav-icon">🎯</span>
                    <span>Tasks</span>
                </a>
                <div class="ep-nav-fab-wrap">
                    <div class="text-center">
                        @if (setting_bool('daily_checkin_enabled', true) && ! ($navCheckedInToday ?? false))
                            <form method="POST" action="{{ route('checkin') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="ep-fab" aria-label="Daily check-in">🪙</button>
                            </form>
                        @else
                            <button type="button" class="ep-fab" disabled aria-label="Checked in">✓</button>
                        @endif
                        <div class="ep-fab-label">Check-in</div>
                    </div>
                </div>
                <a class="ep-nav-item {{ $currentRoute === 'spin' ? 'active' : '' }}" href="{{ route('spin') }}">
                    <span class="ep-nav-icon">🎡</span>
                    <span>Spin</span>
                </a>
                <a class="ep-nav-item" href="{{ route('dashboard') }}#refer">
                    <span class="ep-nav-icon">🎁</span>
                    <span>Invite</span>
                </a>
            </nav>
        @endif
    </div>

    @if (! $isAdmin)
        {{-- Sticky sidebar ad rail (desktop only; JS hides it when no placement is eligible) --}}
        <aside class="ep-sidebar-ad d-none d-xl-block" data-sidebar-ad><x-ad placement="sidebar" /></aside>

        {{-- Interstitial overlay (session-capped server-side; JS shows it only when a placement rendered) --}}
        <div class="ep-interstitial" id="epInterstitial" hidden>
            <div class="ep-interstitial-inner">
                <button type="button" class="ep-interstitial-close" id="epInterstitialClose" aria-label="Close ad">✕</button>
                <x-ad placement="interstitial" />
            </div>
        </div>
    @endif

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/animejs/anime.min.js') }}"></script>
    <script src="{{ asset('js/animations.js') }}"></script>
    <script src="{{ asset('js/ads.js') }}"></script>
    @stack('scripts')
</body>
</html>
