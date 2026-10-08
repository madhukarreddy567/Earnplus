<?php $title = ($provider->exists ? 'Edit' : 'Add') . ' offerwall provider'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="mb-4" data-animate>
        <h1 class="h3 fw-bold mb-0">{{ $title }}</h1>
        <p class="text-muted mb-0">Credentials live in the database — never in code.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4" data-animate>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8" data-animate data-delay="80">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <form method="POST" action="{{ $provider->exists ? route('admin.offerwalls.update', $provider) : route('admin.offerwalls.store') }}">
                        @csrf
                        @if ($provider->exists)
                            @method('PUT')
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="name">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required maxlength="100"
                                       value="{{ old('name', $provider->name) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="slug">Slug</label>
                                <input type="text" class="form-control" id="slug" name="slug" required maxlength="60"
                                       pattern="[a-z0-9-]+" value="{{ old('slug', $provider->slug) }}">
                                <div class="form-text">Lowercase letters, numbers and dashes. Used in the postback URL.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="user_revenue_share">User revenue share (%)</label>
                                <input type="number" class="form-control" id="user_revenue_share" name="user_revenue_share"
                                       required min="0" max="100" step="0.01"
                                       value="{{ old('user_revenue_share', $provider->user_revenue_share) }}">
                                <div class="form-text">Of every payout, this % goes to the user as coins.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="tagline">Tagline</label>
                                <input type="text" class="form-control" id="tagline" name="tagline" maxlength="255"
                                       value="{{ old('tagline', $provider->config['tagline'] ?? '') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="offer_url_template">Offer URL template</label>
                                <input type="text" class="form-control" id="offer_url_template" name="offer_url_template" maxlength="500"
                                       value="{{ old('offer_url_template', $provider->config['offer_url_template'] ?? '') }}"
                                       placeholder="https://provider.com/wall?uid={click_uid}&user={user_id}">
                                <div class="form-text">{click_uid} and {user_id} are substituted per click.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="max_payout_coins">Max payout (coins, display only)</label>
                                <input type="number" class="form-control" id="max_payout_coins" name="max_payout_coins" min="1"
                                       value="{{ old('max_payout_coins', $provider->config['max_payout_coins'] ?? '') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="ip_whitelist">IP whitelist</label>
                                <textarea class="form-control" id="ip_whitelist" name="ip_whitelist" rows="2" maxlength="2000"
                                          placeholder="203.0.113.10&#10;198.51.100.0/24">{{ old('ip_whitelist', $provider->ip_whitelist) }}</textarea>
                                <div class="form-text">One IP or CIDR per line. Empty = allow all signed postbacks.</div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                                           @checked(old('enabled', $provider->enabled))>
                                    <label class="form-check-label fw-semibold" for="enabled">Enabled</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" id="sandbox_mode" name="sandbox_mode" value="1"
                                           @checked(old('sandbox_mode', $provider->sandbox_mode))>
                                    <label class="form-check-label fw-semibold" for="sandbox_mode">Sandbox mode</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-dark">{{ $provider->exists ? 'Save changes' : 'Create provider' }}</button>
                            <a href="{{ route('admin.offerwalls.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($provider->exists)
            <div class="col-lg-4" data-animate data-delay="160">
                <div class="card shadow-sm border-0 rounded-4 mb-3">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold">Postback URL</h2>
                        <code class="small d-block text-break">{{ route('postback.handle', $provider) }}</code>
                        <p class="text-muted small mt-2 mb-0">Paste this into the provider's postback settings.</p>
                    </div>
                </div>
                <div class="card shadow-sm border-0 rounded-4 mb-3">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold">Postback secret</h2>
                        <code class="small d-block text-break">{{ $provider->postback_secret }}</code>
                        <form method="POST" action="{{ route('admin.offerwalls.regenerate-secret', $provider) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm"
                                    data-confirm="Regenerate the secret? The provider dashboard must be updated too.">
                                Regenerate secret
                            </button>
                        </form>
                    </div>
                </div>
                @if (! $provider->sandbox_mode)
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h6 fw-bold text-danger">Danger zone</h2>
                            <form method="POST" action="{{ route('admin.offerwalls.destroy', $provider) }}"
                                  data-confirm="Delete this provider? Clicks and conversions are deleted too.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete provider</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
