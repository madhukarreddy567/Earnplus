<?php $title = 'Ads'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4" data-animate>
        <div>
            <h1 class="h3 fw-bold mb-0">Ads</h1>
            <p class="text-muted mb-0">Networks, placements, impressions and rewarded-ad payouts.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ads.rewards') }}" class="btn btn-outline-dark btn-sm">Reward log</a>
            <a href="{{ route('admin.ads.placements.create') }}" class="btn btn-outline-dark btn-sm">Add placement</a>
            <a href="{{ route('admin.ads.networks.create') }}" class="btn btn-dark btn-sm">Add network</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4" data-animate>{{ session('error') }}</div>
    @endif

    <h2 class="h5 fw-bold mb-3" data-animate>Ad networks</h2>
    <div class="card shadow-sm border-0 rounded-4 mb-5" data-animate data-delay="80">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Network</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Placements</th>
                        <th>Impressions</th>
                        <th>Coins paid</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($networks as $network)
                        @php $ns = $networkStats[$network->id]; @endphp
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $network->name }}</span>
                                <span class="text-muted small d-block">{{ $network->slug }}</span>
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ \App\Models\AdNetwork::typeLabel($network->type) }}</span></td>
                            <td>
                                @if ($network->enabled)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Enabled</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Disabled</span>
                                @endif
                            </td>
                            <td>{{ $network->placements_count }}</td>
                            <td>{{ number_format($ns['impressions_total']) }}</td>
                            <td>{{ number_format($ns['coins_paid']) }} 🪙</td>
                            <td class="text-end">
                                <a href="{{ route('admin.ads.networks.edit', $network) }}" class="btn btn-sm btn-outline-dark">Edit</a>
                                <form method="POST" action="{{ route('admin.ads.networks.destroy', $network) }}" class="d-inline" data-confirm="Delete network {{ $network->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No networks yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="h5 fw-bold mb-3" data-animate>Placements</h2>
    <div class="card shadow-sm border-0 rounded-4 mb-5" data-animate data-delay="120">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Placement</th>
                        <th>Slot</th>
                        <th>Type</th>
                        <th>Device</th>
                        <th>Cap/sess</th>
                        <th>Priority</th>
                        <th>Coins</th>
                        <th>Status</th>
                        <th>Views today / total</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($placements as $placement)
                        @php $s = $stats[$placement->id]; @endphp
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $placement->name }}</span>
                                <span class="text-muted small d-block">{{ $placement->slug }} · {{ $placement->network->name }}</span>
                            </td>
                            <td><code class="small">{{ $placement->slot }}</code></td>
                            <td>{{ \App\Models\AdPlacement::typeLabel($placement->placement_type) }}</td>
                            <td class="text-capitalize">{{ $placement->device }}</td>
                            <td>{{ $placement->frequency_cap_per_session }}</td>
                            <td>{{ $placement->priority }}</td>
                            <td>{{ $placement->coins > 0 ? $placement->coins . ' 🪙' : '—' }}</td>
                            <td>
                                @if ($placement->enabled && $placement->network->enabled)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Live</span>
                                @elseif ($placement->enabled)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Network off</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Disabled</span>
                                @endif
                            </td>
                            <td>{{ number_format($s['impressions_today']) }} / {{ number_format($s['impressions_total']) }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.ads.placements.edit', $placement) }}" class="btn btn-sm btn-outline-dark">Edit</a>
                                <form method="POST" action="{{ route('admin.ads.placements.destroy', $placement) }}" class="d-inline" data-confirm="Delete placement {{ $placement->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No placements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3" data-animate>
        <h2 class="h5 fw-bold mb-0">Recent rewarded payouts</h2>
        <a href="{{ route('admin.ads.rewards') }}" class="btn btn-sm btn-outline-dark">Full log</a>
    </div>
    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="160">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>User</th>
                        <th>Placement</th>
                        <th>Coins</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentRewards as $reward)
                        <tr>
                            <td class="text-muted small">{{ $reward->created_at->format('d M H:i') }}</td>
                            <td>{{ $reward->user->name ?? '—' }}</td>
                            <td>{{ $reward->placement->name }}</td>
                            <td class="fw-semibold">+{{ $reward->coins }} 🪙</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No rewarded payouts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
