<?php $title = 'Withdrawal #' . $withdrawal->id; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 pt-3 pb-5" style="max-width:520px;margin:0 auto;">
    <a href="{{ route('withdraw.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill mb-3" data-animate>← Back</a>

    @if (session('success'))
        <div class="alert alert-success rounded-4" data-animate>{{ session('success') }}</div>
    @endif

    <div class="ep-card p-4 mb-3" data-animate>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 fw-bold mb-0">Withdrawal #{{ $withdrawal->id }}</h1>
            <span class="badge rounded-pill {{ $withdrawal->status === 'completed' ? 'bg-success' : ($withdrawal->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }} fs-6">
                {{ \App\Models\Withdrawal::statusLabel($withdrawal->status) }}
            </span>
        </div>

        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Method</span><strong>{{ $withdrawal->method->name }}</strong>
        </div>
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Coins debited</span><strong>{{ number_format($withdrawal->coins_debited) }} 🪙</strong>
        </div>
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Amount</span><strong>{{ $withdrawal->amountFormatted() }}</strong>
        </div>
        @if ($withdrawal->tax_paise > 0)
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Tax</span><strong>₹{{ number_format($withdrawal->tax_paise / 100, 2) }}</strong>
            </div>
        @endif
        <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">You receive</span><strong class="text-success">{{ $withdrawal->netFormatted() }}</strong>
        </div>
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Requested</span><span>{{ $withdrawal->requested_at->format('d M Y, h:i A') }}</span>
        </div>
        @if ($withdrawal->payout_reference)
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Reference</span><span class="font-monospace">{{ $withdrawal->payout_reference }}</span>
            </div>
        @endif
        @if ($withdrawal->admin_note)
            <div class="alert alert-light rounded-4 small mt-3 mb-0">
                <strong>Note from our team:</strong> {{ $withdrawal->admin_note }}
            </div>
        @endif
    </div>

    <div class="ep-card p-4" data-animate data-delay="120">
        <div class="fw-bold mb-2">Status</div>
        <ul class="ep-timeline">
            @php
                $steps = ['pending' => 'Requested', 'processing' => 'Processing payout', 'completed' => 'Completed'];
                $order = ['pending' => 0, 'approved' => 1, 'processing' => 2, 'completed' => 3, 'failed' => 2, 'rejected' => 0];
                $current = $order[$withdrawal->status] ?? 0;
                $i = 0;
            @endphp
            @foreach ($steps as $key => $label)
                @php $i++; @endphp
                <li class="{{ $i - 1 < $current ? 'done' : ($i - 1 === $current ? 'current' : '') }}">
                    <span class="dot"></span>
                    <span class="small {{ $i - 1 <= $current ? 'fw-semibold' : 'text-muted' }}">{{ $label }}</span>
                </li>
            @endforeach
            @if ($withdrawal->isRejected())
                <li class="current"><span class="dot" style="background:#dc3545;"></span><span class="small fw-semibold text-danger">Rejected — coins refunded</span></li>
            @endif
            @if ($withdrawal->status === 'failed')
                <li class="current"><span class="dot" style="background:#dc3545;"></span><span class="small fw-semibold text-danger">Payout failed — contact support</span></li>
            @endif
        </ul>
    </div>
</div>
@endsection
