<?php $title = 'Verify mobile'; ?>
@extends('layouts.auth')

@section('content')
    <div class="text-center" data-animate data-delay="180">
        <div class="verify-icon mb-3">📱</div>
        <h1 class="h4 fw-bold mb-2">Verify your mobile number</h1>
        <p class="text-muted mb-4">
            We sent a 6-digit code to
            <span class="fw-semibold text-dark">{{ auth()->user()->mobile }}</span>.
            It expires in 10 minutes.
        </p>
    </div>

    <form method="POST" action="{{ route('otp.verify') }}" data-animate data-delay="300">
        @csrf

        <div class="form-floating mb-3">
            <input type="text" name="code" id="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                class="form-control otp-input text-center @error('code') is-invalid @enderror"
                placeholder="000000" required autofocus autocomplete="one-time-code">
            <label for="code">6-digit code</label>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Verify number</button>
    </form>

    <form method="POST" action="{{ route('otp.resend') }}" class="text-center mt-3" data-animate data-delay="360">
        @csrf
        <button type="submit" class="btn btn-link small">Didn't get the code? Resend</button>
    </form>
@endsection
