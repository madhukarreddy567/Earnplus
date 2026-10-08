<?php $title = 'Security center'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mb-4" data-animate>
        <h1 class="h4 fw-bold mb-0">🛡️ Security center</h1>
        <p class="text-muted small mb-0">Login attempts, blocked IPs, proxy settings and emergency lockdown.</p>
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

    <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
        <div class="card-body p-4 border border-danger rounded-4">
            <h2 class="h6 fw-bold text-danger">🚨 Emergency lockdown</h2>
            <p class="small text-muted mb-3">Turns on maintenance mode immediately and logs out <strong>everyone</strong> — all users and all admins, including you. Use only if the site is under attack.</p>
            <form method="POST" action="{{ route('admin.security.lockdown') }}" data-confirm="Activate emergency lockdown? The site goes into maintenance mode and EVERYONE is logged out.">
                @csrf
                <button type="submit" class="btn btn-danger rounded-pill">Activate lockdown</button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold">🚫 IP blocklist</h2>
                    <p class="small text-muted">Single IPs or CIDR ranges. Expired entries stop applying automatically.</p>
                    <form method="POST" action="{{ route('admin.security.blocks.store') }}" class="row g-2 mb-3">
                        @csrf
                        <div class="col-5">
                            <input type="text" name="ip_or_cidr" class="form-control rounded-3" placeholder="1.2.3.4 or 1.2.3.0/24" required>
                        </div>
                        <div class="col-4">
                            <input type="text" name="reason" class="form-control rounded-3" placeholder="Reason (optional)">
                        </div>
                        <div class="col-3">
                            <button type="submit" class="btn btn-dark rounded-pill w-100">Block</button>
                        </div>
                    </form>
                    @if ($blocks->isEmpty())
                        <p class="text-muted small mb-0">No blocked IPs.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($blocks as $block)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <code>{{ $block->ip_or_cidr }}</code>
                                        @if ($block->reason)<div class="small text-muted">{{ $block->reason }}</div>@endif
                                        @if ($block->expires_at)<div class="small text-muted">Expires {{ $block->expires_at->diffForHumans() }}</div>@endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.security.blocks.destroy', $block) }}" data-confirm="Unblock {{ $block->ip_or_cidr }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-success rounded-pill">Unblock</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold">🌐 Proxy &amp; headers</h2>
                    <form method="POST" action="{{ route('admin.security.settings') }}">
                        @csrf
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="trust_proxy_headers" value="1" id="tph" {{ $settings['trust_proxy_headers'] ? 'checked' : '' }}>
                            <label class="form-check-label" for="tph">Trust proxy headers (Cloudflare mode)</label>
                            <div class="form-text">Only enable behind Cloudflare or your own reverse proxy, with the correct ranges below.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Trusted proxy ranges (one per line)</label>
                            <textarea name="trusted_proxy_cidrs" class="form-control rounded-3 font-monospace" rows="4">{{ $settings['trusted_proxy_cidrs'] }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">IP whitelist (never blocked, one per line)</label>
                            <textarea name="security_ip_whitelist" class="form-control rounded-3 font-monospace" rows="2" placeholder="Your office IP, e.g. 203.0.113.7">{{ $settings['security_ip_whitelist'] }}</textarea>
                            <div class="form-text">Whitelisted IPs skip the blocklist. Add your own IP here <em>before</em> blocking aggressively.</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="hsts_enabled" value="1" id="hsts" {{ $settings['hsts_enabled'] ? 'checked' : '' }}>
                            <label class="form-check-label" for="hsts">HSTS header (HTTPS only)</label>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill">Save security settings</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold">⚠️ Security events</h2>
                    @if ($events->isEmpty())
                        <p class="text-muted small mb-0">No events recorded.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm small mb-0">
                                <thead><tr><th>When</th><th>Type</th><th>IP</th><th>Detail</th></tr></thead>
                                <tbody>
                                    @foreach ($events as $event)
                                        <tr>
                                            <td class="text-nowrap">{{ $event->created_at->diffForHumans() }}</td>
                                            <td><span class="badge bg-warning text-dark">{{ $event->type }}</span></td>
                                            <td><code>{{ $event->ip }}</code></td>
                                            <td class="text-muted">{{ Str::limit($event->detail ?? $event->path, 60) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold">🔑 Recent login attempts</h2>
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>When</th><th>E-mail</th><th>Guard</th><th>IP</th><th>Result</th></tr></thead>
                            <tbody>
                                @foreach ($loginLogs as $log)
                                    <tr>
                                        <td class="text-nowrap">{{ $log->created_at->diffForHumans() }}</td>
                                        <td>{{ Str::limit($log->email ?? '—', 28) }}</td>
                                        <td>{{ $log->guard }}</td>
                                        <td><code>{{ $log->ip }}</code></td>
                                        <td>
                                            @if ($log->success)
                                                <span class="badge bg-success">ok</span>
                                            @else
                                                <span class="badge bg-danger">failed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
