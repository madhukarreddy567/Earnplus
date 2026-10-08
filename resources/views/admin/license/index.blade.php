<?php $title = 'License & domain lock'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mb-4" data-animate>
        <h1 class="h4 fw-bold mb-0">🔐 License &amp; domain lock</h1>
        <p class="text-muted small mb-0">Production mode, licensed domain, violation log and the emergency kill switch.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
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

    @php($stateBadge = [
        'ok' => ['success', 'Licensed'],
        'warning' => ['warning', 'Needs attention'],
        'violation' => ['danger', 'Violation'],
        'locked' => ['dark', 'Locked'],
    ][$status['state']] ?? ['secondary', $status['state']])

    {{-- Status --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">License status</h2>
                <span class="badge bg-{{ $stateBadge[0] }} rounded-pill">{{ $stateBadge[1] }}</span>
            </div>
            <div class="row g-3 small">
                <div class="col-6 col-md-3">
                    <div class="text-muted">Mode</div>
                    <div class="fw-bold">{{ ucfirst($status['mode']) }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted">Licensed domain</div>
                    <div class="fw-bold">{{ $status['licensed_domain'] ?? '— not set —' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted">Signature</div>
                    <div class="fw-bold {{ $status['signature_valid'] ? 'text-success' : 'text-danger' }}">
                        {{ $status['signature_valid'] ? 'Valid' : 'MISMATCH' }}
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted">Violations (24h)</div>
                    <div class="fw-bold">{{ number_format($status['violations_24h']) }}</div>
                </div>
            </div>
            @if (count($status['notes']) > 0)
                <ul class="mb-0 mt-3 small text-muted">
                    @foreach ($status['notes'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($status['locked'])
                <div class="alert alert-dark border-0 rounded-4 mt-3 mb-0">
                    <div class="fw-bold">🔐 Kill switch is ACTIVE</div>
                    <div class="small">Locked at {{ $status['locked_at'] }} ({{ $status['locked_reason'] }}). All user routes show the lock page. Use the unlock button below to restore service.</div>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            {{-- Settings --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold">⚙️ Mode &amp; domain</h2>
                    <p class="small text-muted">Development mode is lenient (demo provider visible, checks off). Production enforces the signature and the domain lock.</p>
                    <form method="POST" action="{{ route('admin.license.settings') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold">App mode</label>
                            <select name="app_mode" class="form-select rounded-3">
                                <option value="development" {{ $settings['app_mode'] === 'development' ? 'selected' : '' }}>Development (lenient)</option>
                                <option value="production" {{ $settings['app_mode'] === 'production' ? 'selected' : '' }}>Production (strict)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Licensed domain</label>
                            <input type="text" name="licensed_domain" class="form-control rounded-3" placeholder="example.com" value="{{ old('licensed_domain', $settings['licensed_domain']) }}">
                            <div class="form-text">Bare domain only — no https://, path or port. The www variant is accepted automatically.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Extra allowed domains (optional)</label>
                            <input type="text" name="license_domain_aliases" class="form-control rounded-3" placeholder="staging.example.com, app.example.com" value="{{ old('license_domain_aliases', $settings['license_domain_aliases']) }}">
                            <div class="form-text">Comma-separated. Useful for staging or app subdomains.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Violation block threshold</label>
                            <input type="number" name="license_violation_block_threshold" class="form-control rounded-3" min="0" max="1000" value="{{ old('license_violation_block_threshold', $settings['license_violation_block_threshold']) }}">
                            <div class="form-text">After this many domain/signature violations from one IP in 24h, requests are hard-blocked. 0 disables the block.</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="license_remote_kill_enabled" value="1" class="form-check-input" id="remoteKillToggle" {{ $settings['license_remote_kill_enabled'] ? 'checked' : '' }}>
                            <label class="form-check-label small fw-bold" for="remoteKillToggle">Enable remote kill URL</label>
                            <div class="form-text">When on, a signed URL (below) locks the app instantly from anywhere. Keep it off unless you need it.</div>
                        </div>
                        <button type="submit" class="btn btn-dark rounded-pill">Save settings</button>
                    </form>
                </div>
            </div>

            {{-- Remote kill secret --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold">📡 Remote kill URL</h2>
                    @if ($settings['license_remote_kill_enabled'])
                        <p class="small text-muted">Triggering this URL locks the app immediately. Keep it private — anyone with it can lock your app.</p>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control rounded-3 font-monospace" readonly value="{{ route('license.remote-kill') }}?token={{ app(\App\Services\LicenseService::class)->remoteKillToken() }}" onclick="this.select()">
                        </div>
                    @else
                        <p class="small text-muted">Remote kill is <strong>off</strong>. Turn it on above to generate the URL.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.license.kill-secret') }}" data-confirm="Regenerate the remote-kill secret? The old kill URL will stop working immediately.">
                        @csrf
                        <button type="submit" class="btn btn-outline-dark btn-sm rounded-pill">Regenerate secret</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            {{-- Kill switch --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
                <div class="card-body p-4 border border-danger rounded-4">
                    <h2 class="h6 fw-bold text-danger">🚨 Emergency kill switch</h2>
                    @if ($status['locked'])
                        <p class="small text-muted mb-3">The app is currently <strong>locked</strong>. Unlocking restores service for all users immediately.</p>
                        <form method="POST" action="{{ route('admin.license.unlock') }}" data-confirm="Unlock the app? All user routes will start serving again.">
                            @csrf
                            <button type="submit" class="btn btn-success rounded-pill">Unlock the app</button>
                        </form>
                    @elseif ($confirmKill)
                        <p class="small text-muted mb-3"><strong>This is the final step.</strong> Activating the kill switch locks <strong>every user route</strong> immediately — users see a lock page. You stay signed in and can unlock from this page.</p>
                        <form method="POST" action="{{ route('admin.license.kill') }}" data-confirm="FINAL CONFIRMATION: lock the entire app right now?">
                            @csrf
                            <div class="form-check mb-3">
                                <input type="checkbox" name="acknowledge" value="1" class="form-check-input" id="killAck" required>
                                <label class="form-check-label small fw-bold" for="killAck">I understand this locks all users out immediately.</label>
                            </div>
                            <button type="submit" class="btn btn-danger rounded-pill">Activate kill switch</button>
                            <a href="{{ route('admin.license.index') }}" class="btn btn-outline-secondary rounded-pill ms-2">Cancel</a>
                        </form>
                    @else
                        <p class="small text-muted mb-3">Locks every user route instantly and shows a lock page. Use only in an emergency (abuse, breach, stolen build). Admin login keeps working so you can unlock.</p>
                        <a href="{{ route('admin.license.index', ['confirm_kill' => 1]) }}" class="btn btn-danger rounded-pill">Activate kill switch</a>
                    @endif
                </div>
            </div>

            {{-- Violations --}}
            <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold">📋 Violation log</h2>
                    <form method="GET" action="{{ route('admin.license.index') }}" class="row g-2 mb-3">
                        <div class="col-4">
                            <select name="type" class="form-select form-select-sm rounded-3">
                                <option value="">All types</option>
                                @foreach ($typeLabels as $key => $label)
                                    <option value="{{ $key }}" {{ ($filters['type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-5">
                            <input type="text" name="q" class="form-control form-control-sm rounded-3" placeholder="Search IP or host" value="{{ $filters['q'] ?? '' }}">
                        </div>
                        <div class="col-3">
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill w-100">Filter</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
                            <thead>
                                <tr><th>When</th><th>Type</th><th>IP</th><th>Host</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($violations as $violation)
                                    <tr>
                                        <td class="text-nowrap">{{ $violation->created_at->format('d M H:i') }}</td>
                                        <td><span class="badge bg-secondary rounded-pill">{{ $typeLabels[$violation->type] ?? $violation->type }}</span></td>
                                        <td class="font-monospace">{{ $violation->ip ?? '—' }}</td>
                                        <td class="font-monospace text-truncate" style="max-width:160px;">{{ $violation->host ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted text-center py-3">No violations recorded. Good news.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $violations->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
