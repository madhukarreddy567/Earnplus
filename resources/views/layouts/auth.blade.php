<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ setting('site_name', 'EarnPlus') }}{{ isset($title) ? ' — ' . $title : '' }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🪙</text></svg>">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    <div class="ep-shell">
        <main class="ep-auth-wrap">
            <a href="{{ route('landing') }}" class="ep-auth-brand" data-animate="pop">
                <span class="ep-coin">₹</span>
                <span>{{ setting('site_name', 'EarnPlus') }}</span>
            </a>

            <div class="ep-auth-card" data-animate data-delay="120">
                @if (session('status'))
                    <div class="alert alert-success border-0 rounded-4" data-animate="pop">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger border-0 rounded-4" data-animate="pop">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>

            <p class="text-center mt-4 text-muted small" data-animate data-delay="220">
                © {{ date('Y') }} {{ setting('site_name', 'EarnPlus') }}. {{ setting('site_tagline', 'Earn coins. Redeem rewards.') }}
            </p>
        </main>
    </div>

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/animejs/anime.min.js') }}"></script>
    <script src="{{ asset('js/animations.js') }}"></script>
    @stack('scripts')
</body>
</html>
