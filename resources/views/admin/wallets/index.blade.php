<?php $title = 'Wallets'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4" data-animate>
        <div>
            <h1 class="h3 fw-bold mb-0">Wallets</h1>
            <p class="text-muted mb-0">
                {{ number_format($totals['users']) }} users ·
                {{ number_format($totals['coins']) }} coins in circulation ·
                {{ number_format($totals['lifetime']) }} lifetime earned
            </p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Admin home</a>
    </div>

    <form method="GET" action="{{ route('admin.wallets.index') }}" class="mb-4" data-animate data-delay="100">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Search by name or e-mail…" value="{{ $search }}">
            <button class="btn btn-dark" type="submit">Search</button>
            @if ($search !== '')
                <a class="btn btn-outline-secondary" href="{{ route('admin.wallets.index') }}">Clear</a>
            @endif
        </div>
    </form>

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="160">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th class="text-end">Balance</th>
                        <th class="text-end">Lifetime earned</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($wallets as $wallet)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $wallet->user->name }}</div>
                                <div class="text-muted small">{{ $wallet->user->email }}</div>
                            </td>
                            <td class="text-end fw-bold">{{ number_format($wallet->coins) }} 🪙</td>
                            <td class="text-end text-muted">{{ number_format($wallet->lifetime_earned) }} 🪙</td>
                            <td class="text-end">
                                <a href="{{ route('admin.wallets.show', $wallet->user) }}" class="btn btn-sm btn-outline-dark">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No wallets found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $wallets->links() }}</div>
</div>
@endsection
