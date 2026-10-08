<?php $title = 'Promotions'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4" data-animate>
        <div>
            <h1 class="h3 fw-bold mb-0">Promotions</h1>
            <p class="text-muted mb-0">Festival multipliers that boost coin earnings.</p>
        </div>
        <a href="{{ route('admin.promotions.create') }}" class="btn btn-dark btn-sm">Add promotion</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning border-0 shadow-sm rounded-4" data-animate>{{ session('warning') }}</div>
    @endif

    @php
        $groups = [
            'active' => ['label' => 'Active now', 'color' => 'success'],
            'upcoming' => ['label' => 'Upcoming', 'color' => 'info'],
            'expired' => ['label' => 'Expired', 'color' => 'secondary'],
            'disabled' => ['label' => 'Disabled', 'color' => 'dark'],
        ];
    @endphp

    @foreach ($groups as $key => $group)
        <h2 class="h6 fw-bold text-uppercase text-muted mt-4 mb-2" data-animate>
            {{ $group['label'] }}
            <span class="badge bg-{{ $group['color'] }} rounded-pill">{{ count($grouped[$key]) }}</span>
        </h2>
        <div class="card shadow-sm border-0 rounded-4 mb-2" data-animate data-delay="60">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Promotion</th>
                            <th>Multiplier</th>
                            <th>Scope</th>
                            <th>Window</th>
                            <th>Priority</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grouped[$key] as $promotion)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $promotion->name }}</div>
                                    <div class="text-muted small">
                                        {{ $promotion->badgeText() }} badge
                                        @if ($promotion->provider)
                                            · {{ $promotion->provider->name }} only
                                        @endif
                                    </div>
                                </td>
                                <td><span class="fw-bold text-success">{{ rtrim(rtrim(number_format((float) $promotion->multiplier, 2), '0'), '.') }}x</span></td>
                                <td>{{ $promotion->scopeLabel() }}</td>
                                <td class="small">
                                    {{ $promotion->starts_at->format('d M Y, H:i') }}
                                    <span class="text-muted">→</span>
                                    {{ $promotion->ends_at->format('d M Y, H:i') }}
                                </td>
                                <td>{{ $promotion->priority }}</td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="{{ route('admin.promotions.toggle', $promotion) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $promotion->enabled ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                            {{ $promotion->enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.promotions.edit', $promotion) }}" class="btn btn-sm btn-outline-dark">Edit</a>
                                    <form method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" class="d-inline" data-confirm="Delete this promotion?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">None.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
