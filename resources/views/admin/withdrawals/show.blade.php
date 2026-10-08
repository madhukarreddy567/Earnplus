<?php $title = 'Withdrawal #' . $withdrawal->id; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5" style="max-width:760px;">
    <a href="{{ route('admin.withdrawals.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill mb-3" data-animate>← All withdrawals</a>

    @if (session('success'))
        <div class="alert alert-success rounded-4" data-animate>{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 rounded-4 p-4 mb-3" data-animate>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 fw-bold mb-0">Withdrawal #{{ $withdrawal->id }}</h1>
            <span class="badge rounded-pill {{ $withdrawal->status === 'completed' ? 'bg-success' : ($withdrawal->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }} fs-6">
                {{ \App\Models\Withdrawal::statusLabel($withdrawal->status) }}
            </span>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">User</div>
                <div class="fw-semibold">{{ $withdrawal->user->name }}</div>
                <div class="text-muted small">{{ $withdrawal->user->email }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Method</div>
                <div class="fw-semibold">{{ $withdrawal->method->name }}</div>
                <div class="text-muted small">{{ $withdrawal->currency }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Coins debited</div>
                <div class="fw-bold">{{ number_format($withdrawal->coins_debited) }} 🪙</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Gross / Tax</div>
                <div class="fw-semibold">{{ $withdrawal->amountFormatted() }} / ₹{{ number_format($withdrawal->tax_paise / 100, 2) }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Net payout</div>
                <div class="fw-bold text-success">{{ $withdrawal->netFormatted() }}</div>
            </div>
        </div>

        <hr>

        <div class="fw-bold mb-2">Payout details</div>
        <dl class="row small mb-0">
            @foreach (($withdrawal->details ?? []) as $key => $value)
                <dt class="col-sm-4 text-muted">{{ $withdrawal->method->detailLabel($key) }}</dt>
                <dd class="col-sm-8 font-monospace">{{ $value }}</dd>
            @endforeach
        </dl>

        @if ($withdrawal->payout_reference)
            <div class="mt-2 small"><span class="text-muted">Payout reference:</span> <span class="font-monospace">{{ $withdrawal->payout_reference }}</span></div>
        @endif
        @if ($withdrawal->admin_note)
            <div class="alert alert-light rounded-4 small mt-3 mb-0"><strong>Admin note:</strong> {{ $withdrawal->admin_note }}</div>
        @endif
        @if (! empty($withdrawal->meta['driver_message']))
            <div class="alert alert-info rounded-4 small mt-3 mb-0"><strong>Driver:</strong> {{ $withdrawal->meta['driver_message'] }}</div>
        @endif
        <div class="text-muted small mt-3">
            Requested {{ $withdrawal->requested_at->format('d M Y, h:i A') }}
            @if ($withdrawal->processed_at)
                · Processed {{ $withdrawal->processed_at->format('d M Y, h:i A') }}
            @endif
            @if (! empty($withdrawal->meta['processed_by_admin_email']))
                · by {{ $withdrawal->meta['processed_by_admin_email'] }}
            @endif
        </div>
    </div>

    @if ($withdrawal->isPending())
        <div class="card shadow-sm border-0 rounded-4 p-4" data-animate data-delay="120">
            <div class="fw-bold mb-3">Review this request</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('admin.withdrawals.approve', $withdrawal) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small">Admin note (optional)</label>
                            <input type="text" name="admin_note" class="form-control" maxlength="1000" placeholder="e.g. paid via UPI">
                        </div>
                        <button type="submit" class="btn btn-success w-100">✓ Approve &amp; pay out</button>
                        <div class="text-muted small mt-1">Runs the payout driver for {{ $withdrawal->method->name }}.</div>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="POST" action="{{ route('admin.withdrawals.reject', $withdrawal) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small">Rejection reason (shown to user)</label>
                            <input type="text" name="reason" class="form-control" maxlength="1000" placeholder="e.g. invalid UPI ID">
                        </div>
                        <button type="submit" class="btn btn-outline-danger w-100">✕ Reject &amp; refund coins</button>
                        <div class="text-muted small mt-1">The exact {{ number_format($withdrawal->coins_debited) }} coins return to the user's wallet.</div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
