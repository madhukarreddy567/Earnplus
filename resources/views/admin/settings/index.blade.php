<?php $title = 'Site settings'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mb-4" data-animate>
        <h1 class="h4 fw-bold mb-0">Site settings</h1>
        <p class="text-muted small mb-0">General, feature toggles and maintenance mode. Changes apply instantly — no deploy needed.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('status') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4" data-animate>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <ul class="nav nav-pills gap-2 mb-4" data-animate>
        <li class="nav-item"><a class="nav-link {{ $tab === 'general' ? 'active' : '' }}" href="{{ route('admin.settings.index', ['tab' => 'general']) }}">⚙️ General</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'features' ? 'active' : '' }}" href="{{ route('admin.settings.index', ['tab' => 'features']) }}">🧩 Features</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'maintenance' ? 'active' : '' }}" href="{{ route('admin.settings.index', ['tab' => 'maintenance']) }}">🛠️ Maintenance</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'spin' ? 'active' : '' }}" href="{{ route('admin.settings.index', ['tab' => 'spin']) }}">🎡 Spin wheel</a></li>
    </ul>

    <form method="POST" action="{{ route('admin.settings.update') }}" data-animate>
        @csrf
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="ep-card mb-4">
            @if ($tab === 'general')
                <h2 class="h6 fw-bold mb-3">General</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold" for="site_name">{{ $labels['site_name'] }}</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" value="{{ old('site_name', $values['site_name']) }}" maxlength="60" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold" for="site_tagline">{{ $labels['site_tagline'] }}</label>
                        <input type="text" class="form-control" id="site_tagline" name="site_tagline" value="{{ old('site_tagline', $values['site_tagline']) }}" maxlength="120">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold" for="support_email">{{ $labels['support_email'] }}</label>
                        <input type="email" class="form-control" id="support_email" name="support_email" value="{{ old('support_email', $values['support_email']) }}" maxlength="120" placeholder="support@example.com">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold" for="default_currency">{{ $labels['default_currency'] }}</label>
                        <select class="form-select" id="default_currency" name="default_currency">
                            <option value="INR" {{ old('default_currency', $values['default_currency']) === 'INR' ? 'selected' : '' }}>INR (₹)</option>
                            <option value="USD" {{ old('default_currency', $values['default_currency']) === 'USD' ? 'selected' : '' }}>USD ($)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold" for="default_language">{{ $labels['default_language'] }}</label>
                        <select class="form-select" id="default_language" name="default_language">
                            <option value="en" {{ old('default_language', $values['default_language']) === 'en' ? 'selected' : '' }}>English</option>
                            <option value="hi" {{ old('default_language', $values['default_language']) === 'hi' ? 'selected' : '' }}>Hindi</option>
                            <option value="te" {{ old('default_language', $values['default_language']) === 'te' ? 'selected' : '' }}>Telugu</option>
                        </select>
                    </div>
                </div>
            @elseif ($tab === 'features')
                <h2 class="h6 fw-bold mb-1">Feature &amp; module toggles</h2>
                <p class="text-muted small mb-3">Switching a module off disables it everywhere instantly — user routes return 404 and UI links hide.</p>
                @foreach ($keys as $key => $group)
                    <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                        <div>
                            <div class="fw-bold small">{{ $labels[$key] }}</div>
                            @if (isset($hints[$key]))
                                <div class="text-muted small">{{ $hints[$key] }}</div>
                            @endif
                        </div>
                        <div class="form-check form-switch ms-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="sw_{{ $key }}" name="{{ $key }}" value="1" {{ old($key, $values[$key]) == '1' ? 'checked' : '' }}>
                            <label class="form-check-label visually-hidden" for="sw_{{ $key }}">{{ $labels[$key] }}</label>
                        </div>
                    </div>
                @endforeach
            @elseif ($tab === 'maintenance')
                <h2 class="h6 fw-bold mb-3">Maintenance mode</h2>
                <div class="d-flex justify-content-between align-items-start py-2 border-bottom mb-3">
                    <div>
                        <div class="fw-bold small">{{ $labels['maintenance_mode'] }}</div>
                        <div class="text-muted small">{{ $hints['maintenance_mode'] }}</div>
                    </div>
                    <div class="form-check form-switch ms-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="sw_maintenance_mode" name="maintenance_mode" value="1" {{ old('maintenance_mode', $values['maintenance_mode']) == '1' ? 'checked' : '' }}>
                        <label class="form-check-label visually-hidden" for="sw_maintenance_mode">{{ $labels['maintenance_mode'] }}</label>
                    </div>
                </div>
                <label class="form-label small fw-bold" for="maintenance_message">{{ $labels['maintenance_message'] }}</label>
                <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3" maxlength="500">{{ old('maintenance_message', $values['maintenance_message']) }}</textarea>
            @elseif ($tab === 'spin')
                <h2 class="h6 fw-bold mb-1">Spin wheel</h2>
                <p class="text-muted small mb-3">Prize range, daily limit, win odds, and the rewarded-ad claim gate.</p>
                @foreach ($keys as $key => $group)
                    @if (str_ends_with($key, '_enabled'))
                        <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                            <div>
                                <div class="fw-bold small">{{ $labels[$key] }}</div>
                                @if (isset($hints[$key]))
                                    <div class="text-muted small">{{ $hints[$key] }}</div>
                                @endif
                            </div>
                            <div class="form-check form-switch ms-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="sw_{{ $key }}" name="{{ $key }}" value="1" {{ old($key, $values[$key]) == '1' ? 'checked' : '' }}>
                                <label class="form-check-label visually-hidden" for="sw_{{ $key }}">{{ $labels[$key] }}</label>
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label small fw-bold" for="{{ $key }}">{{ $labels[$key] }}</label>
                            <input type="number" class="form-control" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" min="0">
                            @if (isset($hints[$key]))
                                <div class="form-text">{{ $hints[$key] }}</div>
                            @endif
                        </div>
                    @endif
                @endforeach
            @endif

            <div class="mt-4">
                <button type="submit" class="btn btn-dark rounded-pill px-4">💾 Save {{ $tab }} settings</button>
            </div>
        </div>
    </form>
</div>
@endsection
