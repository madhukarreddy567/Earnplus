<?php $title = 'Forgot password'; ?>
@extends('layouts.auth')

@section('content')
    <h1 class="h4 fw-bold mb-1" data-animate data-delay="180">Forgot your password?</h1>
    <p class="text-muted mb-4" data-animate data-delay="240">
        Enter your e-mail address and we'll send you a reset link.
    </p>

    <form method="POST" action="{{ route('password.email') }}" data-animate data-delay="300">
        @csrf

        <div class="form-floating mb-3">
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                placeholder="name@example.com" value="{{ old('email') }}" required autofocus autocomplete="email">
            <label for="email">E-mail address</label>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Send reset link</button>
    </form>

    <p class="text-center mt-4 mb-0" data-animate data-delay="360">
        <a href="{{ route('login') }}">Back to log in</a>
    </p>
@endsection
