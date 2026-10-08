<?php $title = 'Admin dashboard'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mb-4" data-animate>
        <h1 class="h4 fw-bold mb-0">Admin dashboard</h1>
        <p class="text-muted small mb-0">Signed in as {{ auth('admin')->user()->name }} ({{ auth('admin')->user()->role }})</p>
    </div>

    @if (! empty($cronAlerts))
        <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4" data-animate>
            <div class="fw-bold mb-1">⚠️ Background jobs need attention</div>
            <ul class="mb-2 small">
                @foreach ($cronAlerts as $alert)
                    <li>{{ $alert }}</li>
                @endforeach
            </ul>
            <a href="{{ route('admin.crons.index') }}" class="btn btn-sm btn-warning rounded-pill">Open scheduled jobs</a>
        </div>
    @endif

    @if (! empty($licenseAlerts))
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4" data-animate>
            <div class="fw-bold mb-1">🔐 License needs attention</div>
            <ul class="mb-2 small">
                @foreach ($licenseAlerts as $alert)
                    <li>{{ $alert }}</li>
                @endforeach
            </ul>
            <a href="{{ route('admin.license.index') }}" class="btn btn-sm btn-danger rounded-pill">Open license page</a>
        </div>
    @endif

    {{-- Key numbers --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['👥', 'Users', number_format($stats['users']['total']), $stats['users']['today'] . ' today · ' . $stats['users']['active_7d'] . ' active (7d)'],
            ['🪙', 'Coins outstanding', number_format($stats['coins']['outstanding_coins']), number_format($stats['coins']['total_credits']) . ' credited all time'],
            ['💰', 'Net margin', format_rupees($stats['revenue']['net_margin_rupees']), format_rupees($stats['revenue']['offerwall_rupees']) . ' offerwall · ' . format_rupees($stats['revenue']['paid_out_rupees']) . ' paid'],
            ['📢', 'Ad impressions', number_format($stats['impressions']['total']), number_format($stats['impressions']['rewarded']) . ' rewarded'],
        ] as $i => $stat)
            <div class="col-6 col-md-3" data-animate data-delay="{{ $i * 80 }}">
                <div class="ep-card h-100 text-center">
                    <div class="fs-4">{{ $stat[0] }}</div>
                    <div class="h5 fw-bold mb-0 mt-1">{{ $stat[1] }}</div>
                    <div class="text-muted small">{{ $stat[2] }}</div>
                    <div class="text-muted small">{{ $stat[3] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Pending withdrawals widget --}}
    <div class="ep-card mb-4" data-animate>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">⏳ Pending withdrawals ({{ $stats['pending_withdrawals']['count'] }})</h2>
            <a href="{{ $stats['pending_withdrawals']['queue_url'] }}" class="btn btn-sm btn-outline-dark rounded-pill">Open queue</a>
        </div>
        @if (count($stats['pending_withdrawals']['items']) === 0)
            <p class="text-muted small mb-0">No pending withdrawals. 🎉</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="text-muted small">
                        <tr><th>User</th><th>Method</th><th class="text-end">Coins</th><th class="text-end">Payout</th><th>Requested</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['pending_withdrawals']['items'] as $w)
                            <tr>
                                <td>{{ $w['user'] }}</td>
                                <td class="text-muted small">{{ $w['method'] }}</td>
                                <td class="text-end">{{ number_format($w['coins']) }}</td>
                                <td class="text-end fw-bold">{{ format_rupees($w['rupees']) }}</td>
                                <td class="text-muted small">{{ $w['requested_at'] }}</td>
                                <td class="text-end"><a href="{{ $w['review_url'] }}" class="btn btn-sm btn-dark rounded-pill">Review</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="row g-3 mb-4">
        {{-- Registrations chart (pure CSS bars, no CDN) --}}
        <div class="col-md-6" data-animate>
            <div class="ep-card h-100">
                <h2 class="h6 fw-bold mb-1">Registrations — last 30 days</h2>
                <p class="text-muted small mb-3">{{ number_format($stats['users']['this_week']) }} this week · {{ number_format($stats['users']['this_month']) }} this month · {{ number_format($stats['users']['referrals']) }} came via referral</p>
                @php($max = max(1, max($stats['users']['daily'])))
                <div class="d-flex align-items-end gap-1" style="height:110px;" role="img" aria-label="Daily registrations chart">
                    @foreach ($stats['users']['daily'] as $day => $count)
                        <div class="flex-fill rounded-top" title="{{ $day }}: {{ $count }}" style="height:{{ round($count / $max * 100) }}%;min-height:3px;background:var(--ep-primary, #198754);opacity:{{ $count > 0 ? 1 : 0.15 }};"></div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between text-muted small mt-1">
                    <span>{{ array_key_first($stats['users']['daily']) }}</span>
                    <span>{{ array_key_last($stats['users']['daily']) }}</span>
                </div>
            </div>
        </div>

        {{-- Coin economy --}}
        <div class="col-md-6" data-animate>
            <div class="ep-card h-100">
                <h2 class="h6 fw-bold mb-3">Coin economy by source</h2>
                @php($srcLabels = ['signup_bonus' => 'Signup', 'referral_bonus' => 'Referral', 'daily_checkin' => 'Check-in', 'spin' => 'Spin', 'task_offerwall' => 'Tasks', 'rewarded_ad' => 'Ads', 'admin_adjust' => 'Admin', 'withdrawal' => 'Withdrawal', 'withdrawal_refund' => 'Refund'])
                @foreach (['credits_by_source' => 'Earned ✅', 'debits_by_source' => 'Spent ❌'] as $bucket => $label)
                    <div class="text-muted small fw-bold mb-1">{{ $label }}</div>
                    @if (empty($stats['coins'][$bucket]))
                        <p class="text-muted small">Nothing yet.</p>
                    @else
                        @foreach ($stats['coins'][$bucket] as $source => $total)
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span>{{ $srcLabels[$source] ?? $source }}</span>
                                <span class="fw-bold">{{ number_format($total) }}</span>
                            </div>
                        @endforeach
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Revenue --}}
        <div class="col-md-6" data-animate>
            <div class="ep-card h-100">
                <h2 class="h6 fw-bold mb-3">Revenue</h2>
                <div class="d-flex justify-content-between small py-1 border-bottom"><span>Offerwall earnings</span><span class="fw-bold">{{ format_rupees($stats['revenue']['offerwall_rupees']) }}</span></div>
                <div class="d-flex justify-content-between small py-1 border-bottom"><span>Paid out (approved+)</span><span class="fw-bold">{{ format_rupees($stats['revenue']['paid_out_rupees']) }}</span></div>
                <div class="d-flex justify-content-between small py-1 border-bottom"><span>Awaiting payout (pending)</span><span class="fw-bold text-warning">{{ format_rupees($stats['revenue']['pending_outflow_rupees']) }}</span></div>
                <div class="d-flex justify-content-between small py-1"><span>Net margin</span><span class="fw-bold {{ $stats['revenue']['net_margin_rupees'] >= 0 ? 'text-success' : 'text-danger' }}">{{ format_rupees($stats['revenue']['net_margin_rupees']) }}</span></div>
                <hr>
                <div class="text-muted small fw-bold mb-1">Offerwall earnings by provider</div>
                @forelse ($stats['revenue']['offerwall_by_provider'] as $row)
                    <div class="d-flex justify-content-between small py-1 border-bottom">
                        <span>{{ $row['provider'] }} <span class="text-muted">({{ $row['conversions'] }} conversions)</span></span>
                        <span class="fw-bold">{{ format_rupees($row['rupees']) }}</span>
                    </div>
                @empty
                    <p class="text-muted small">No credited conversions yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Impressions --}}
        <div class="col-md-6" data-animate>
            <div class="ep-card h-100">
                <h2 class="h6 fw-bold mb-3">Ad impressions</h2>
                <div class="text-muted small fw-bold mb-1">By network</div>
                @forelse ($stats['impressions']['by_network'] as $network => $total)
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span>{{ $network }}</span><span class="fw-bold">{{ number_format($total) }}</span></div>
                @empty
                    <p class="text-muted small">No impressions yet.</p>
                @endforelse
                <div class="text-muted small fw-bold mb-1 mt-3">By device</div>
                @foreach ($stats['impressions']['by_device'] as $device => $total)
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span>{{ $device }}</span><span class="fw-bold">{{ number_format($total) }}</span></div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Module cards --}}
    <h2 class="h6 fw-bold mb-3" data-animate>Manage</h2>
    <div class="row g-3">
        @foreach ([
            ['⚙️', 'Site settings', 'General, feature toggles, module switches and maintenance mode.', route('admin.settings.index'), 'Open settings'],
            ['📄', 'Policy pages', 'Terms, privacy, refund and about — rich text with version history.', route('admin.policies.index'), 'Edit policies'],
            ['👥', 'Admin accounts', 'Manage staff logins (super admin only).', route('admin.admins.index'), 'View admins'],
            ['🪙', 'Coins & wallets', 'User balances, manual adjustments and the full coin ledger.', route('admin.wallets.index'), 'View wallets'],
            ['📢', 'Ads', 'Ad networks, placements, impressions and rewarded payouts.', route('admin.ads.index'), 'Manage ads'],
            ['🧱', 'Offerwalls', 'Task providers, signed postbacks, click tracking and the conversion log.', route('admin.offerwalls.index'), 'Manage offerwalls'],
            ['💸', 'Withdrawals', 'Payout methods, the pending queue and the full withdrawal log.', route('admin.withdrawals.index'), 'Manage withdrawals'],
            ['🎉', 'Promotions', 'Festival multipliers that boost coin earnings.', route('admin.promotions.index'), 'Manage promotions'],
            ['🎨', 'Branding & design', 'Logo, banners, artwork, colors, theme and layout.', route('admin.branding.index'), 'Manage branding'],
            ['🛡️', 'Security center', 'Login logs, IP blocklist, proxy settings and emergency lockdown.', route('admin.security.index'), 'Open security'],
            ['⏰', 'Scheduled jobs', 'Background cron jobs: bonus rollovers, payout retries, log cleanup.', route('admin.crons.index'), 'Open jobs'],
            ['🔐', 'License & domain lock', 'Production mode, licensed domain, violation log and kill switch.', route('admin.license.index'), 'Open license'],
        ] as $i => $card)
            <div class="col-md-6" data-animate data-delay="{{ 100 + $i * 60 }}">
                <div class="ep-card h-100">
                    <div class="ep-qa-icon mb-3">{{ $card[0] }}</div>
                    <h2 class="h6 fw-bold mb-1">{{ $card[1] }}</h2>
                    <p class="text-muted small mb-3">{{ $card[2] }}</p>
                    <a href="{{ $card[3] }}" class="btn btn-dark btn-sm rounded-pill px-3">{{ $card[4] }}</a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
