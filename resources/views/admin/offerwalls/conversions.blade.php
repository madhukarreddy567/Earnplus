<?php $title = 'Offerwall conversions'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="mb-4" data-animate>
        <h1 class="h3 fw-bold mb-0">Conversion log</h1>
        <p class="text-muted mb-0">Every postback, credited or not. Pending ones can be credited or rejected manually.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 rounded-4 mb-3" data-animate data-delay="60">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.offerwalls.conversions') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold" for="f-provider">Provider</label>
                    <select class="form-select form-select-sm" id="f-provider" name="provider">
                        <option value="">All providers</option>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->id }}" @selected((string) ($filters['provider'] ?? '') === (string) $provider->id)>{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold" for="f-status">Status</label>
                    <select class="form-select form-select-sm" id="f-status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark btn-sm w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="100">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Provider</th>
                        <th>User</th>
                        <th>Tx ID</th>
                        <th>Payout</th>
                        <th>User coins</th>
                        <th>Status</th>
                        <th>Flags</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conversions as $conversion)
                        <tr>
                            <td>{{ $conversion->id }}</td>
                            <td>{{ $conversion->provider->name }}</td>
                            <td>{{ $conversion->user?->email ?? '—' }}</td>
                            <td><code class="small">{{ \Illuminate\Support\Str::limit($conversion->provider_tx_id, 24) }}</code></td>
                            <td>{{ number_format($conversion->payout_coins) }}</td>
                            <td>{{ number_format($conversion->user_coins) }} 🪙</td>
                            <td>
                                @if ($conversion->status === 'credited')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Credited</span>
                                @elseif ($conversion->status === 'pending')
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Pending</span>
                                @elseif ($conversion->status === 'rejected')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Rejected</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Duplicate</span>
                                @endif
                            </td>
                            <td>
                                @foreach (($conversion->meta['fraud_flags'] ?? []) as $flag)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $flag }}</span>
                                @endforeach
                            </td>
                            <td class="text-end">
                                @if ($conversion->isPending())
                                    <form method="POST" action="{{ route('admin.offerwalls.conversions.credit', $conversion) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">Credit</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.offerwalls.conversions.reject', $conversion) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>
                                    </form>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No conversions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($conversions->hasPages())
            <div class="card-footer bg-white border-0">
                {{ $conversions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
