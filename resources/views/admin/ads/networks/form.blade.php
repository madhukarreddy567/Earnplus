<?php $title = ($network->exists ? 'Edit' : 'Add') . ' ad network'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5" style="max-width: 760px;">
    <div class="mb-4" data-animate>
        <a href="{{ route('admin.ads.index') }}" class="text-decoration-none small">← Back to Ads</a>
        <h1 class="h3 fw-bold mb-0 mt-2">{{ $network->exists ? 'Edit' : 'Add' }} ad network</h1>
        <p class="text-muted mb-0">Networks stay <strong>disabled</strong> until you paste real ad code into a placement.</p>
    </div>

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="100">
        <div class="card-body p-4">
            <form method="POST" action="{{ $network->exists ? route('admin.ads.networks.update', $network) : route('admin.ads.networks.store') }}">
                @csrf
                @if ($network->exists)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="name">Name</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name', $network->name) }}" required maxlength="100" placeholder="Adsterra">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="slug">Slug</label>
                    <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug"
                        value="{{ old('slug', $network->slug) }}" required maxlength="60" pattern="[a-z0-9-]+"
                        placeholder="adsterra">
                    <div class="form-text">Lowercase letters, numbers and dashes only.</div>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="type">Network type</label>
                    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                        @foreach (\App\Models\AdNetwork::TYPES as $type)
                            <option value="{{ $type }}" {{ old('type', $network->type) === $type ? 'selected' : '' }}>
                                {{ \App\Models\AdNetwork::typeLabel($type) }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1"
                        {{ old('enabled', $network->enabled) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="enabled">Enabled</label>
                    <div class="form-text">Only enabled networks can serve placements.</div>
                </div>

                <?php
                $allSchemas = [];
                foreach (\App\Models\AdNetwork::TYPES as $schemaType) {
                    $schemaFields = \App\Models\AdNetwork::configSchema($schemaType);
                    if ($schemaFields !== []) {
                        $allSchemas[$schemaType] = $schemaFields;
                    }
                }
                $currentConfig = is_array($network->config ?? null) ? $network->config : [];
                ?>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Network credentials / IDs</label>
                    <div class="form-text mb-2">Pasted here by the owner — for Unity/AdMob these IDs are served to the mobile app via /api/config. Never commit real keys to code.</div>
                    @foreach ($allSchemas as $schemaType => $schemaFields)
                        <div class="config-group border rounded-3 p-3 mb-2" data-config-type="{{ $schemaType }}" style="{{ old('type', $network->type) === $schemaType ? '' : 'display:none;' }}">
                            <div class="fw-semibold small mb-2">{{ \App\Models\AdNetwork::typeLabel($schemaType) }}</div>
                            @foreach ($schemaFields as $configKey => $configLabel)
                                <div class="mb-2">
                                    <label class="form-label small mb-1" for="config_{{ $configKey }}">{{ $configLabel }}</label>
                                    <input type="text" class="form-control form-control-sm @error('config.'.$configKey) is-invalid @enderror" id="config_{{ $configKey }}" name="config[{{ $configKey }}]"
                                        value="{{ old('config.'.$configKey, $currentConfig[$configKey] ?? '') }}" maxlength="255" autocomplete="off">
                                    @error('config.'.$configKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <script>
                    document.getElementById('type').addEventListener('change', function () {
                        var selected = this.value;
                        document.querySelectorAll('.config-group').forEach(function (el) {
                            el.style.display = el.getAttribute('data-config-type') === selected ? '' : 'none';
                        });
                    });
                </script>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-dark">{{ $network->exists ? 'Save changes' : 'Create network' }}</button>
                    <a href="{{ route('admin.ads.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
