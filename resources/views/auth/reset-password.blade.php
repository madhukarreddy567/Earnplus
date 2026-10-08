<?php $title = 'Reset password'; ?>
@extends('layouts.auth')

@section('content')
    <h1 class="h4 fw-bold mb-1" data-animate data-delay="180">Set a new password</h1>
    <p class="text-muted mb-4" data-animate data-delay="240">Choose a strong password (min 8 characters).</p>

    <form method="POST" action="{{ route('password.update') }}" data-animate data-delay="300">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-floating mb-3">
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                placeholder="name@example.com" value="{{ old('email', request()->query('email')) }}" required autofocus autocomplete="email">
            <label for="email">E-mail address</label>
        </div>

        <div class="form-floating mb-3">
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror"
                placeholder="New password" required autocomplete="new-password">
            <label for="password">New password</label>
        </div>

        <div class="form-floating mb-3">
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control"
                placeholder="Confirm new password" required autocomplete="new-password">
            <label for="password_confirmation">Confirm new password</label>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Reset password</button>
    </form>
@endsection
