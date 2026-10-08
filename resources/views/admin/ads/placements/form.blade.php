<?php $title = ($placement->exists ? 'Edit' : 'Add') . ' ad placement'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5" style="max-width: 860px;">
    <div class="mb-4" data-animate>
        <a href="{{ route('admin.ads.index') }}" class="text-decoration-none small">← Back to Ads</a>
        <h1 class="h3 fw-bold mb-0 mt-2">{{ $placement->exists ? 'Edit' : 'Add' }} ad placement</h1>
        <p class="text-muted mb-0">A placement ties one network's ad code to one slot on the site.</p>
    </div>

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="100">
        <div class="card-body p-4">
            <form method="POST" action="{{ $placement->exists ? route('admin.ads.placements.update', $placement) : route('admin.ads.placements.store') }}">
                @csrf
                @if ($placement->exists)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="name">Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name', $placement->name) }}" required maxlength="100" placeholder="Adsterra dashboard banner">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="slug">Slug</label>
                        <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug"
                            value="{{ old('slug', $placement->slug) }}" required maxlength="60" pattern="[a-z0-9-]+"
                            placeholder="adsterra-dashboard-banner">
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="network_id">Network</label>
                        <select class="form-select @error('network_id') is-invalid @enderror" id="network_id" name="network_id" required>
                            @foreach ($networks as $network)
                                <option value="{{ $network->id }}" {{ (int) old('network_id', $placement->network_id) === $network->id ? 'selected' : '' }}>
                                    {{ $network->name }}{{ $network->enabled ? '' : ' (disabled)' }}
                                </option>
                            @endforeach
                        </select>
                        @error('network_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="slot">Slot</label>
                        <input type="text" class="form-control @error('slot') is-invalid @enderror" id="slot" name="slot"
                            value="{{ old('slot', $placement->slot) }}" required maxlength="60" list="slot-list"
                            placeholder="dashboard-banner">
                        <datalist id="slot-list">
                            @foreach ($slots as $slot)
                                <option value="{{ $slot }}"></option>
                            @endforeach
                        </datalist>
                        <div class="form-text">Where on the site it renders: <code>&lt;x-ad placement="slot" /&gt;</code>.</div>
                        @error('slot')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="placement_type">Placement type</label>
                        <select class="form-select @error('placement_type') is-invalid @enderror" id="placement_type" name="placement_type" required>
                            @foreach (\App\Models\AdPlacement::TYPES as $type)
                                <option value="{{ $type }}" {{ old('placement_type', $placement->placement_type) === $type ? 'selected' : '' }}>
                                    {{ \App\Models\AdPlacement::typeLabel($type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('placement_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="device">Device</label>
                        <select class="form-select @error('device') is-invalid @enderror" id="device" name="device" required>
                            @foreach (\App\Models\AdPlacement::DEVICES as $device)
                                <option value="{{ $device }}" {{ old('device', $placement->device) === $device ? 'selected' : '' }} class="text-capitalize">
                                    {{ ucfirst($device) }}
                                </option>
                            @endforeach
                        </select>
                        @error('device')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="pages">Pages <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control @error('pages') is-invalid @enderror" id="pages" name="pages"
                            value="{{ old('pages', is_array($placement->pages) ? implode(', ', $placement->pages) : '') }}"
                            maxlength="1000" placeholder="dashboard, tasks">
                        <div class="form-text">Comma-separated route names. Empty = show on every page where the slot exists.</div>
                        @error('pages')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="frequency_cap_per_session">Frequency cap / session</label>
                        <input type="number" class="form-control @error('frequency_cap_per_session') is-invalid @enderror"
                            id="frequency_cap_per_session" name="frequency_cap_per_session"
                            value="{{ old('frequency_cap_per_session', $placement->frequency_cap_per_session) }}"
                            required min="1" max="100">
                        @error('frequency_cap_per_session')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="priority">Priority</label>
                        <input type="number" class="form-control @error('priority') is-invalid @enderror"
                            id="priority" name="priority"
                            value="{{ old('priority', $placement->priority) }}" required min="1" max="1000">
                        <div class="form-text">Higher wins rotation more often.</div>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="coins">Coins <span class="text-muted fw-normal">(rewarded only)</span></label>
                        <input type="number" class="form-control @error('coins') is-invalid @enderror"
                            id="coins" name="coins"
                            value="{{ old('coins', $placement->coins) }}" required min="0" max="100000">
                        @error('coins')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="custom_code">Ad code</label>
                        <textarea class="form-control font-monospace @error('custom_code') is-invalid @enderror" id="custom_code"
                            name="custom_code" rows="8" maxlength="20000"
                            placeholder="Paste the network's ad code / script here…">{{ old('custom_code', $placement->custom_code) }}</textarea>
                        <div class="form-text">Rendered exactly as pasted — only admins can edit this field.</div>
                        @error('custom_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1"
                                {{ old('enabled', $placement->enabled) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="enabled">Enabled</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-dark">{{ $placement->exists ? 'Save changes' : 'Create placement' }}</button>
                    <a href="{{ route('admin.ads.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
