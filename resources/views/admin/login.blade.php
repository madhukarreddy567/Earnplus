<?php $title = 'Admin log in'; ?>
@extends('layouts.auth')

@section('content')
    <div class="text-center mb-4" data-animate data-delay="180">
        <div class="ep-qa-icon mx-auto mb-3" style="width:60px;height:60px;font-size:1.8rem;background:var(--ep-ink);">🛡️</div>
        <h1 class="h4 fw-bold mb-1">Admin access</h1>
        <p class="text-muted mb-0">Restricted area. Authorized staff only.</p>
    </div>

    <form method="POST" action="{{ route('admin.login') }}" data-animate data-delay="300">
        @csrf

        <div class="form-floating mb-3">
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                placeholder="admin@example.com" value="{{ old('email') }}" required autofocus autocomplete="email">
            <label for="email">Admin e-mail</label>
        </div>

        <div class="form-floating mb-3">
            <input type="password" name="password" id="password" class="form-control"
                placeholder="Password" required autocomplete="current-password">
            <label for="password">Password</label>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>

        <button type="submit" class="btn btn-dark w-100 py-2 fw-bold" style="border-radius:14px;">Log in to admin</button>
    </form>
@endsection
