<?php $title = 'Create account'; ?>
@extends('layouts.auth')

@section('content')
    <h1 class="h4 fw-bold mb-1" data-animate data-delay="160">Create your account</h1>
    <p class="text-muted mb-4" data-animate data-delay="200">
        Join {{ setting('site_name', 'EarnPlus') }} and grab your
        <span class="fw-semibold text-dark">{{ setting_int('signup_bonus_coins', 50) }} coin</span> signup bonus.
    </p>

    @include('components.google-button', ['ref' => $referralCode ?? null])

    @if (setting_bool('email_auth_enabled', false))
        @if (google_auth_enabled())
            <div class="ep-divider" data-animate data-delay="240">or with e-mail</div>
        @endif

        <form method="POST" action="{{ route('register') }}" data-animate data-delay="280">
            @csrf

            <div class="form-floating mb-3">
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                    placeholder="Your name" value="{{ old('name') }}" required autofocus autocomplete="name">
                <label for="name">Full name</label>
            </div>

            <div class="form-floating mb-3">
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                    placeholder="name@example.com" value="{{ old('email') }}" required autocomplete="email">
                <label for="email">E-mail address</label>
            </div>

            <div class="form-floating mb-3">
                <input type="tel" name="mobile" id="mobile" class="form-control @error('mobile') is-invalid @enderror"
                    placeholder="Mobile number" value="{{ old('mobile') }}" autocomplete="tel">
                <label for="mobile">Mobile number <span class="text-muted">(optional)</span></label>
            </div>

            <div class="form-floating mb-3">
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror"
                    placeholder="Password" required autocomplete="new-password">
                <label for="password">Password <span class="text-muted">(min 8 characters)</span></label>
            </div>

            <div class="form-floating mb-3">
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control"
                    placeholder="Confirm password" required autocomplete="new-password">
                <label for="password_confirmation">Confirm password</label>
            </div>

            @if (setting_bool('referral_enabled', true))
                <div class="form-floating mb-3">
                    <input type="text" name="referral_code" id="referral_code"
                        class="form-control @error('referral_code') is-invalid @enderror"
                        placeholder="Referral code" value="{{ old('referral_code', $referralCode ?? '') }}"
                        maxlength="12" style="text-transform: uppercase" autocomplete="off">
                    <label for="referral_code">Referral code <span class="text-muted">(optional)</span></label>
                </div>
            @endif

            @if (setting_bool('recaptcha_enabled') && setting('recaptcha_site_key', '') !== '')
                <div class="g-recaptcha mb-3" data-sitekey="{{ setting('recaptcha_site_key') }}"></div>
                @push('scripts')
                    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                @endpush
            @endif

            <button type="submit" class="btn btn-primary w-100 py-2">Create account</button>
        </form>

        <p class="text-center mt-4 mb-0" data-animate data-delay="340">
            Already have an account?
            <a href="{{ route('login') }}" class="fw-semibold">Log in</a>
        </p>
    @elseif (! google_auth_enabled())
        <div class="alert alert-info border-0 rounded-4 mb-0" data-animate data-delay="240">
            Sign-up is being set up — please check back soon.
        </div>
    @endif
@endsection
