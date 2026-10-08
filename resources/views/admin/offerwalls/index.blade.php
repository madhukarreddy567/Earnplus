<?php $title = 'Offerwalls'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4" data-animate>
        <div>
            <h1 class="h3 fw-bold mb-0">Offerwalls</h1>
            <p class="text-muted mb-0">Providers, postback URLs and conversion stats.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.offerwalls.conversions') }}" class="btn btn-outline-dark btn-sm">Conversion log</a>
            <a href="{{ route('admin.offerwalls.create') }}" class="btn btn-dark btn-sm">Add provider</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4" data-animate>{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="100">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Revenue share</th>
                        <th>Clicks</th>
                        <th>Conversions</th>
                        <th>Coins paid</th>
                        <th>Pending</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($providers as $provider)
                        @php $s = $stats[$provider->id]; @endphp
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $provider->name }}</span>
                                <span class="text-muted small d-block">{{ $provider->slug }}</span>
                                @if ($provider->sandbox_mode)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Sandbox</span>
                                @endif
                            </td>
                            <td>
                                @if ($provider->enabled)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Enabled</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Disabled</span>
                                @endif
                            </td>
                            <td>{{ rtrim(rtrim(number_format($provider->user_revenue_share, 2), '0'), '.') }}%</td>
                            <td>{{ number_format($s['clicks']) }}</td>
                            <td>{{ number_format($s['conversions']) }}</td>
                            <td>{{ number_format($s['coins_paid']) }} 🪙</td>
                            <td>
                                @if ($s['pending'] > 0)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">{{ $s['pending'] }}</span>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.offerwalls.edit', $provider) }}" class="btn btn-outline-dark btn-sm">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No providers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
