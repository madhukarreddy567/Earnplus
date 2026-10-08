<?php $title = 'Payout methods'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4" data-animate>
        <div>
            <h1 class="h3 fw-bold mb-0">Payout methods</h1>
            <p class="text-muted mb-0">Enable/disable methods, set limits and paste API keys.</p>
        </div>
        <a href="{{ route('admin.withdrawals.index') }}" class="btn btn-outline-secondary btn-sm">← Withdrawals</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success rounded-4" data-animate>{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="100">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Method</th><th>Status</th><th class="text-end">Min</th><th class="text-end">Max</th><th>Driver</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($methods as $method)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $method->name }}</div>
                                <div class="text-muted small">{{ $method->type }} · {{ $method->currency }}</div>
                            </td>
                            <td>
                                @if ($method->enabled)
                                    <span class="badge rounded-pill bg-success">Enabled</span>
                                @else
                                    <span class="badge rounded-pill bg-secondary">Disabled</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $method->formatAmount($method->min_amount) }}</td>
                            <td class="text-end">{{ $method->formatAmount($method->max_amount) }}</td>
                            <td class="small">
                                @if ($method->driver()->isLive())
                                    <span class="badge rounded-pill bg-info text-dark">API live</span>
                                @else
                                    <span class="text-muted">Manual</span>
                                @endif
                            </td>
                            <td class="text-end"><a href="{{ route('admin.withdrawals.methods.edit', $method) }}" class="btn btn-sm btn-outline-dark">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
