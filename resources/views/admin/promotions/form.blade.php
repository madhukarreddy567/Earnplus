<?php $title = ($promotion->exists ? 'Edit' : 'Add') . ' promotion'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="mb-4" data-animate>
        <h1 class="h3 fw-bold mb-0">{{ $title }}</h1>
        <p class="text-muted mb-0">Multipliers apply to the base coin amount; stacking is decided by the <code>promotion_stacking</code> setting.</p>
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
    @if ($overlapWarning)
        <div class="alert alert-warning border-0 shadow-sm rounded-4" data-animate>{{ $overlapWarning }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8" data-animate data-delay="80">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <form method="POST" action="{{ $promotion->exists ? route('admin.promotions.update', $promotion) : route('admin.promotions.store') }}">
                        @csrf
                        @if ($promotion->exists)
                            @method('PUT')
                        @endif

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold" for="name">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required maxlength="120"
                                       value="{{ old('name', $promotion->name) }}"
                                       placeholder="e.g. Diwali Dhamaka">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="slug">Slug</label>
                                <input type="text" class="form-control" id="slug" name="slug" maxlength="140"
                                       pattern="[a-z0-9-]*" value="{{ old('slug', $promotion->slug) }}">
                                <div class="form-text">Auto-generated from the name when blank.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="multiplier">Multiplier</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="multiplier" name="multiplier"
                                           required min="1.1" max="10" step="0.1"
                                           value="{{ old('multiplier', $promotion->multiplier) }}">
                                    <span class="input-group-text">x</span>
                                </div>
                                <div class="form-text">1.1 to 10. Coins are rounded down.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="scope">Scope</label>
                                <select class="form-select" id="scope" name="scope" required>
                                    @foreach (\App\Models\Promotion::SCOPES as $scope)
                                        <option value="{{ $scope }}" {{ old('scope', $promotion->scope) === $scope ? 'selected' : '' }}>
                                            {{ \App\Models\Promotion::SCOPE_LABELS[$scope] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="priority">Priority</label>
                                <input type="number" class="form-control" id="priority" name="priority"
                                       min="0" max="1000" value="{{ old('priority', $promotion->priority) }}">
                                <div class="form-text">Lower runs first; banner shows the lowest.</div>
                            </div>
                            <div class="col-md-6" id="provider-row">
                                <label class="form-label fw-semibold" for="provider_id">Provider <span class="text-muted fw-normal">(task scope only)</span></label>
                                <select class="form-select" id="provider_id" name="provider_id">
                                    <option value="">All providers</option>
                                    @foreach ($providers as $provider)
                                        <option value="{{ $provider->id }}" {{ (string) old('provider_id', $promotion->provider_id) === (string) $provider->id ? 'selected' : '' }}>
                                            {{ $provider->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="badge_text">Badge text</label>
                                <input type="text" class="form-control" id="badge_text" name="badge_text" maxlength="16"
                                       value="{{ old('badge_text', $promotion->badge_text) }}" placeholder="2X">
                                <div class="form-text">Shown on tiles and cards. Auto-derived from the multiplier when blank.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="starts_at">Starts at</label>
                                <input type="datetime-local" class="form-control" id="starts_at" name="starts_at" required
                                       value="{{ old('starts_at', $promotion->starts_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="ends_at">Ends at</label>
                                <input type="datetime-local" class="form-control" id="ends_at" name="ends_at" required
                                       value="{{ old('ends_at', $promotion->ends_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="banner_title">Banner title</label>
                                <input type="text" class="form-control" id="banner_title" name="banner_title" maxlength="160"
                                       value="{{ old('banner_title', $promotion->banner_title) }}"
                                       placeholder="e.g. Diwali Dhamaka — 2X coins!">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="banner_subtitle">Banner subtitle</label>
                                <input type="text" class="form-control" id="banner_subtitle" name="banner_subtitle" maxlength="255"
                                       value="{{ old('banner_subtitle', $promotion->banner_subtitle) }}">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                                           {{ old('enabled', $promotion->enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="enabled">Enabled</label>
                                </div>
                                <div class="form-text">Disabled promotions never apply, even inside their window.</div>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-dark">{{ $promotion->exists ? 'Save changes' : 'Create promotion' }}</button>
                                <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function () {
    var scope = document.getElementById('scope');
    var row = document.getElementById('provider-row');
    function sync() {
        row.style.display = scope.value === 'task_offerwall' ? '' : 'none';
    }
    scope.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
