<?php $title = 'Branding & design'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mb-4" data-animate>
        <h1 class="h4 fw-bold mb-0">Branding &amp; design</h1>
        <p class="text-muted small mb-0">Logo, banners and artwork — plus colors, theme and layout. Changes apply instantly.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4" data-animate>{{ session('error') }}</div>
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
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'media' ? 'active' : '' }}" href="{{ route('admin.branding.index', ['tab' => 'media']) }}">🖼️ Media</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'design' ? 'active' : '' }}" href="{{ route('admin.branding.index', ['tab' => 'design']) }}">🎨 Design</a>
        </li>
    </ul>

    @if ($tab === 'media')
        {{-- Live preview of what's currently live --}}
        <div class="ep-card mb-4" data-animate>
            <h2 class="h6 fw-bold mb-3">Live preview</h2>
            <div class="row g-3 align-items-center">
                <div class="col-md-3 text-center">
                    <div class="text-muted small mb-2">Logo (navbar)</div>
                    @if ($logoUrl = branding_url('branding_logo'))
                        <img src="{{ $logoUrl }}" alt="Logo" style="max-height:44px;max-width:160px;object-fit:contain;">
                    @else
                        <span class="text-muted small">Default coin + name</span>
                    @endif
                </div>
                <div class="col-md-3 text-center">
                    <div class="text-muted small mb-2">Favicon (32px actual size)</div>
                    @if ($faviconUrl = branding_url('branding_favicon_32'))
                        <img src="{{ $faviconUrl }}" alt="Favicon" width="32" height="32" style="image-rendering:auto;">
                    @else
                        <span class="text-muted small">Default 🪙</span>
                    @endif
                </div>
                <div class="col-md-3 text-center">
                    <div class="text-muted small mb-2">Default avatar</div>
                    @if ($avatarUrl = branding_url('branding_avatar'))
                        <img src="{{ $avatarUrl }}" alt="Avatar" width="64" height="64" class="rounded-circle" style="object-fit:cover;">
                    @else
                        <span class="text-muted small">Initial letter</span>
                    @endif
                </div>
                <div class="col-md-3 text-center">
                    <div class="text-muted small mb-2">Email header</div>
                    @if ($emailUrl = branding_url('branding_email_header'))
                        <img src="{{ $emailUrl }}" alt="Email header" style="max-width:100%;max-height:48px;object-fit:contain;">
                    @else
                        <span class="text-muted small">Site name text</span>
                    @endif
                </div>
                <div class="col-12">
                    <div class="text-muted small mb-2">Banner (dashboard &amp; landing)</div>
                    @if ($bannerUrl = branding_url('branding_banner_1200'))
                        <img src="{{ $bannerUrl }}" alt="Banner" class="rounded-4 w-100" style="max-height:160px;object-fit:cover;">
                    @else
                        <span class="text-muted small">Default gradient banner</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-3">
            @foreach ($types as $type => $meta)
                <div class="col-md-6" data-animate>
                    <div class="ep-card h-100">
                        <h2 class="h6 fw-bold mb-1">{{ $meta['label'] }}</h2>
                        <p class="text-muted small mb-3">{{ $meta['hint'] }}</p>

                        @php($current = null)
                        @foreach (\App\Services\BrandingService::settingsFor($type) as $key)
                            @php($u = branding_url($key))
                            @if ($u && $current === null)
                                @php($current = $u)
                            @endif
                        @endforeach

                        @if ($current)
                            <img src="{{ $current }}" alt="{{ $meta['label'] }}" class="rounded-3 mb-3 d-block"
                                 style="max-height:90px;max-width:100%;object-fit:contain;background:#f1f4f7;">
                        @endif

                        <form method="POST" action="{{ route('admin.branding.media.store') }}" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                            @csrf
                            <input type="hidden" name="type" value="{{ $type }}">
                            <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required
                                   class="form-control form-control-sm" style="max-width:220px;">
                            <button type="submit" class="btn btn-dark btn-sm">Upload</button>
                            @if ($current)
                                <button type="button" class="btn btn-outline-danger btn-sm ep-remove-asset"
                                        data-type="{{ $type }}" data-label="{{ strtolower($meta['label']) }}">Remove</button>
                            @endif
                        </form>
                        {{-- DELETE spoof for the remove button above --}}
                        @if ($current)
                            <form id="ep-del-{{ $type }}" method="POST" action="{{ route('admin.branding.media.destroy', $type) }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <script nonce="{{ csp_nonce() }}">
            // Route the "Remove" buttons through the hidden DELETE forms.
            document.querySelectorAll('.ep-remove-asset').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!confirm('Remove this ' + btn.dataset.label + ' and go back to the default?')) {
                        return;
                    }
                    document.getElementById('ep-del-' + btn.dataset.type).submit();
                });
            });
        </script>
    @else
        <form method="POST" action="{{ route('admin.branding.design.store') }}" data-animate>
            @csrf
            <div class="ep-card mb-4">
                <h2 class="h6 fw-bold mb-3">Colors</h2>
                <div class="row g-3">
                    @foreach (['design_primary' => 'Primary (buttons, hero, accents)', 'design_accent' => 'Accent (links, highlights)', 'design_hero_from' => 'Hero gradient start', 'design_hero_to' => 'Hero gradient end'] as $key => $label)
                        <div class="col-md-3 col-6">
                            <label class="form-label fw-semibold small" for="{{ $key }}">{{ $label }}</label>
                            <input type="color" class="form-control form-control-color w-100" id="{{ $key }}" name="{{ $key }}"
                                   value="{{ $design[$key] }}" style="height:44px;">
                            <div class="form-text font-monospace">{{ $design[$key] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ep-card mb-4">
                <h2 class="h6 fw-bold mb-3">Layout &amp; type</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small" for="font_scale">Font size</label>
                        <select class="form-select" id="font_scale" name="font_scale">
                            @foreach (['90' => 'Compact (90%)', '100' => 'Normal (100%)', '110' => 'Large (110%)'] as $v => $l)
                                <option value="{{ $v }}" {{ $design['font_scale'] === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small" for="button_shape">Button shape</label>
                        <select class="form-select" id="button_shape" name="button_shape">
                            @foreach (['pill' => 'Pill', 'rounded' => 'Rounded', 'square' => 'Square'] as $v => $l)
                                <option value="{{ $v }}" {{ $design['button_shape'] === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small" for="theme">Theme</label>
                        <select class="form-select" id="theme" name="theme">
                            @foreach (['light' => 'Light', 'dark' => 'Dark'] as $v => $l)
                                <option value="{{ $v }}" {{ $design['theme'] === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small" for="background_style">Background image style</label>
                        <select class="form-select" id="background_style" name="background_style">
                            @foreach (['cover' => 'Cover (full-bleed)', 'blur' => 'Blur (soft backdrop)'] as $v => $l)
                                <option value="{{ $v }}" {{ $design['background_style'] === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Applies when a background image is uploaded.</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="animations_enabled" name="animations_enabled" value="1"
                                   {{ $design['animations_enabled'] === '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold small" for="animations_enabled">Animations enabled</label>
                            <div class="form-text">Master switch for anime.js motion (reduced-motion is always respected).</div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-dark px-4">Save design</button>
        </form>
    @endif
</div>
@endsection
