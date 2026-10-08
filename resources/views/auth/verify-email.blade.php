<?php $title = 'Verify e-mail'; ?>
@extends('layouts.auth')

@section('content')
    <div class="text-center" data-animate data-delay="180">
        <div class="verify-icon mb-3">✉️</div>
        <h1 class="h4 fw-bold mb-2">Check your inbox</h1>
        <p class="text-muted mb-4">
            We sent a verification link to
            <span class="fw-semibold text-dark">{{ auth()->user()->email }}</span>.
            Click it to unlock your dashboard.
        </p>
    </div>

    <form method="POST" action="{{ route('verification.send') }}" data-animate data-delay="300">
        @csrf
        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Resend verification e-mail</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center mt-3" data-animate data-delay="360">
        @csrf
        <button type="submit" class="btn btn-link text-muted small">Log out</button>
    </form>
@endsection
