<?php $title = 'Withdrawals'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4" data-animate>
        <div>
            <h1 class="h3 fw-bold mb-0">Withdrawals</h1>
            <p class="text-muted mb-0">
                <span class="badge rounded-pill bg-warning text-dark">{{ $pendingCount }} pending</span>
                payout requests awaiting review
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.withdrawals.methods') }}" class="btn btn-outline-dark btn-sm">Payout methods</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Admin home</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success rounded-4" data-animate>{{ session('success') }}</div>
    @endif

    {{-- Pending queue --}}
    <h2 class="h5 fw-bold mb-3" data-animate>Pending queue</h2>
    <div class="card shadow-sm border-0 rounded-4 mb-5" data-animate data-delay="80">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>User</th><th>Method</th><th class="text-end">Net payout</th><th class="text-end">Coins</th><th>Requested</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($pending as $w)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $w->user->name }}</div>
                                <div class="text-muted small">{{ $w->user->email }}</div>
                            </td>
                            <td>{{ $w->method->name }}</td>
                            <td class="text-end fw-bold text-success">{{ $w->netFormatted() }}</td>
                            <td class="text-end text-muted">{{ number_format($w->coins_debited) }}</td>
                            <td class="small">{{ $w->requested_at->diffForHumans() }}</td>
                            <td class="text-end"><a href="{{ route('admin.withdrawals.show', $w) }}" class="btn btn-sm btn-dark">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No pending withdrawals. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Full log with filters --}}
    <h2 class="h5 fw-bold mb-3" data-animate>Full log</h2>
    <form method="GET" action="{{ route('admin.withdrawals.index') }}" class="row g-2 mb-3" data-animate data-delay="100">
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ \App\Models\Withdrawal::statusLabel($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <select name="method_id" class="form-select">
                <option value="">All methods</option>
                @foreach ($methods as $m)
                    <option value="{{ $m->id }}" {{ (string) $methodId === (string) $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-dark" type="submit">Filter</button>
            @if ($status !== '' || $methodId !== '')
                <a class="btn btn-outline-secondary" href="{{ route('admin.withdrawals.index') }}">Clear</a>
            @endif
        </div>
    </form>

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="140">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>User</th><th>Method</th><th class="text-end">Net</th><th>Status</th><th>Requested</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($withdrawals as $w)
                        <tr>
                            <td class="text-muted">{{ $w->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $w->user->name }}</div>
                                <div class="text-muted small">{{ $w->user->email }}</div>
                            </td>
                            <td>{{ $w->method->name }}</td>
                            <td class="text-end fw-bold">{{ $w->netFormatted() }}</td>
                            <td><span class="badge rounded-pill {{ $w->status === 'completed' ? 'bg-success' : ($w->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ \App\Models\Withdrawal::statusLabel($w->status) }}</span></td>
                            <td class="small">{{ $w->requested_at->format('d M Y') }}</td>
                            <td class="text-end"><a href="{{ route('admin.withdrawals.show', $w) }}" class="btn btn-sm btn-outline-dark">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No withdrawals found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $withdrawals->links() }}</div>
</div>
@endsection
