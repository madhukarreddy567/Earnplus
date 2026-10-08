<?php $title = 'Edit ' . $method->name; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5" style="max-width:720px;">
    <a href="{{ route('admin.withdrawals.methods') }}" class="btn btn-sm btn-outline-secondary rounded-pill mb-3" data-animate>← Payout methods</a>

    <h1 class="h3 fw-bold mb-1" data-animate>Edit: {{ $method->name }}</h1>
    <p class="text-muted mb-4" data-animate>Type <code>{{ $method->type }}</code> · {{ $method->currency }} · amounts in smallest unit (paise/cents).</p>

    @if (session('success'))
        <div class="alert alert-success rounded-4" data-animate>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger rounded-4" data-animate>{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger rounded-4" data-animate>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-4 p-4" data-animate data-delay="100">
        <form method="POST" action="{{ route('admin.withdrawals.methods.update', $method) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Display name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $method->name) }}" required maxlength="80">
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled"
                       {{ old('enabled', $method->enabled) ? 'checked' : '' }}>
                <label class="form-check-label" for="enabled">Enabled (users can pick this method)</label>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Min amount <span class="text-muted small">(paise/cents)</span></label>
                    <input type="number" name="min_amount" class="form-control" value="{{ old('min_amount', $method->min_amount) }}" required min="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Max amount <span class="text-muted small">(paise/cents)</span></label>
                    <input type="number" name="max_amount" class="form-control" value="{{ old('max_amount', $method->max_amount) }}" required min="1">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Sort order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $method->sort_order) }}" required min="0">
            </div>

            <div class="mb-3">
                <label class="form-label">API config <span class="text-muted small">(JSON — API keys for auto payouts; empty values = manual mode)</span></label>
                <textarea name="config" class="form-control font-monospace" rows="6" placeholder='{"key_id": "..."}'>{{ old('config', $method->config ? json_encode($method->config, JSON_PRETTY_PRINT) : '') }}</textarea>
                @if (in_array($method->type, ['cashfree', 'razorpay', 'payu'], true))
                    <div class="form-text">
                        @if ($method->type === 'cashfree')
                            Keys: <code>cashfree_client_id</code>, <code>cashfree_client_secret</code>, <code>cashfree_env</code> ("sandbox"/"prod") — from the Cashfree dashboard → Payouts.
                        @elseif ($method->type === 'razorpay')
                            Keys: <code>razorpay_key_id</code>, <code>razorpay_key_secret</code>, <code>razorpay_account_number</code> — from the RazorpayX dashboard.
                        @else
                            Keys: <code>payu_merchant_key</code>, <code>payu_merchant_salt</code>, <code>payu_base_url</code> — from the PayU dashboard.
                        @endif
                    </div>
                @endif
            </div>

            <button type="submit" class="btn btn-dark w-100">Save method</button>
        </form>
    </div>
</div>
@endsection
