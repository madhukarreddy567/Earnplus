<?php $title = 'Rewarded-ad payouts'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="mb-4" data-animate>
        <a href="{{ route('admin.ads.index') }}" class="text-decoration-none small">← Back to Ads</a>
        <h1 class="h3 fw-bold mb-0 mt-2">Rewarded-ad payouts</h1>
        <p class="text-muted mb-0">Every coin payout from rewarded ads, newest first.</p>
    </div>

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="100">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>User</th>
                        <th>Placement</th>
                        <th>Network</th>
                        <th>Coins</th>
                        <th>Idempotency key</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rewards as $reward)
                        <tr>
                            <td class="text-muted small">{{ $reward->created_at->format('d M Y H:i') }}</td>
                            <td>
                                <span class="fw-semibold">{{ $reward->user->name ?? '—' }}</span>
                                <span class="text-muted small d-block">{{ $reward->user->email ?? '' }}</span>
                            </td>
                            <td>{{ $reward->placement->name }}</td>
                            <td class="text-muted small">{{ $reward->placement->network->name ?? '—' }}</td>
                            <td class="fw-semibold">+{{ $reward->coins }} 🪙</td>
                            <td><code class="small">{{ $reward->idempotency_key }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No rewarded payouts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rewards->hasPages())
            <div class="card-body">
                {{ $rewards->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
