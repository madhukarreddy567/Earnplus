<?php $title = 'Log in'; ?>
@extends('layouts.auth')

@section('content')
    <h1 class="h4 fw-bold mb-1" data-animate data-delay="160">Welcome back</h1>
    <p class="text-muted mb-4" data-animate data-delay="200">Log in to keep earning coins.</p>

    @include('components.google-button')

    @if (setting_bool('email_auth_enabled', false))
        @if (google_auth_enabled())
            <div class="ep-divider" data-animate data-delay="240">or with e-mail</div>
        @endif

        <form method="POST" action="{{ route('login') }}" data-animate data-delay="280">
            @csrf

            <div class="form-floating mb-3">
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                    placeholder="name@example.com" value="{{ old('email') }}" required autofocus autocomplete="email">
                <label for="email">E-mail address</label>
            </div>

            <div class="form-floating mb-3">
                <input type="password" name="password" id="password" class="form-control"
                    placeholder="Password" required autocomplete="current-password">
                <label for="password">Password</label>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
            </div>

            @if (setting_bool('recaptcha_enabled') && setting('recaptcha_site_key', '') !== '')
                <div class="g-recaptcha mb-3" data-sitekey="{{ setting('recaptcha_site_key') }}"></div>
                @push('scripts')
                    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                @endpush
            @endif

            <button type="submit" class="btn btn-primary w-100 py-2">Log in</button>
        </form>

        <p class="text-center mt-4 mb-0" data-animate data-delay="340">
            New here?
            <a href="{{ route('register') }}" class="fw-semibold">Create an account</a>
        </p>
    @elseif (! google_auth_enabled())
        <div class="alert alert-info border-0 rounded-4 mb-0" data-animate data-delay="240">
            Sign-in is being set up — please check back soon.
        </div>
    @endif
@endsection
