<?php $title = 'Wallet — ' . $user->name; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('status') }}</div>
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

    <div class="d-flex justify-content-between align-items-center mb-4" data-animate>
        <div>
            <h1 class="h3 fw-bold mb-0">{{ $user->name }}</h1>
            <p class="text-muted mb-0">{{ $user->email }} · joined {{ $user->created_at->format('d M Y') }}</p>
        </div>
        <a href="{{ route('admin.wallets.index') }}" class="btn btn-outline-secondary btn-sm">← Wallets</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4" data-animate data-delay="100">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body p-4 text-center">
                    <p class="text-muted text-uppercase small fw-semibold mb-1">Balance</p>
                    <p class="display-6 fw-bold mb-0">{{ number_format($user->wallet->coins ?? 0) }} 🪙</p>
                    <p class="text-muted mb-0">≈ {{ format_rupees($user->rupeeBalance()) }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-8" data-animate data-delay="160">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold">Manual adjustment <span class="badge bg-warning text-dark">super admin</span></h2>
                    <p class="text-muted small">A reason is mandatory — it is stored on the ledger row with your admin id.</p>
                    <form method="POST" action="{{ route('admin.wallets.adjust', $user) }}" class="row g-2">
                        @csrf
                        <div class="col-md-3">
                            <select name="direction" class="form-select" required>
                                <option value="credit">Credit (+)</option>
                                <option value="debit">Debit (−)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="amount" class="form-control" min="1" max="10000000" placeholder="Coins" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="reason" class="form-control" maxlength="500" placeholder="Reason (required)" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-dark w-100">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="220">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <h2 class="h5 fw-bold mb-0">Transaction log</h2>
                <form method="GET" class="d-flex gap-2">
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All types</option>
                        <option value="credit" {{ ($filters['type'] ?? '') === 'credit' ? 'selected' : '' }}>Credits</option>
                        <option value="debit" {{ ($filters['type'] ?? '') === 'debit' ? 'selected' : '' }}>Debits</option>
                    </select>
                    <select name="source" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All sources</option>
                        @foreach (\App\Models\CoinTransaction::SOURCES as $source)
                            <option value="{{ $source }}" {{ ($filters['source'] ?? '') === $source ? 'selected' : '' }}>
                                {{ \App\Models\CoinTransaction::sourceLabel($source) }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Reference / meta</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Balance after</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $tx)
                            <tr>
                                <td class="text-muted small">{{ $tx->created_at->format('d M Y, H:i') }}</td>
                                <td>{{ \App\Models\CoinTransaction::sourceLabel($tx->source) }}</td>
                                <td class="small text-muted">
                                    @if ($tx->meta && isset($tx->meta['reason']))
                                        {{ $tx->meta['reason'] }}
                                        <span class="d-block">by {{ $tx->meta['admin_email'] ?? 'admin' }}</span>
                                    @elseif ($tx->reference)
                                        <code>{{ $tx->reference }}</code>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end fw-bold {{ $tx->isCredit() ? 'text-success' : 'text-danger' }}">
                                    {{ $tx->isCredit() ? '+' : '−' }}{{ number_format($tx->amount) }}
                                </td>
                                <td class="text-end text-muted">{{ number_format($tx->balance_after) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No transactions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $transactions->links() }}</div>
        </div>
    </div>
</div>
@endsection
