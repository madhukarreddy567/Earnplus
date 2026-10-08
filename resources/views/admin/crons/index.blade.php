<?php $title = 'Scheduled jobs'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mb-4" data-animate>
        <h1 class="h4 fw-bold mb-0">⏰ Scheduled jobs</h1>
        <p class="text-muted small mb-0">Background maintenance: bonus rollovers, payout retries, log cleanup. Jobs never overlap themselves.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4" data-animate>{{ session('error') }}</div>
    @endif

    {{-- Heartbeat: is the system cron alive? --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
        <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
            <div class="fs-3">{{ $heartbeatAlive ? '💚' : '💔' }}</div>
            <div class="flex-grow-1">
                <h2 class="h6 fw-bold mb-1">System cron {{ $heartbeatAlive ? 'is running' : 'looks stopped' }}</h2>
                <p class="small text-muted mb-0">
                    @if ($heartbeatAt)
                        Last heartbeat: {{ $heartbeatAt->diffForHumans() }} ({{ $heartbeatAt->toDateTimeString() }}).
                    @else
                        No heartbeat recorded yet.
                    @endif
                    @unless ($heartbeatAlive)
                        Add this line to the server's crontab so jobs run on their own:
                    @endunless
                </p>
                @unless ($heartbeatAlive)
                    <code class="d-block mt-2 p-2 bg-light rounded-3 small text-break">{{ $setupSnippet }}</code>
                @endunless
            </div>
        </div>
    </div>

    {{-- Job list --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
        <div class="card-body p-4">
            <h2 class="h6 fw-bold mb-3">Jobs</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="text-muted small">
                        <tr><th>Job</th><th>Schedule</th><th>Last run</th><th>Status</th><th>Next run</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $status = $row['latest']?->status;
                                $badge = match ($status) {
                                    'ok' => 'bg-success',
                                    'warning' => 'bg-warning text-dark',
                                    'failed' => 'bg-danger',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row['title'] }}</div>
                                    <div class="text-muted small">{{ $row['description'] }}</div>
                                </td>
                                <td class="small text-nowrap">{{ $row['schedule_label'] }}</td>
                                <td class="small">
                                    @if ($row['latest'])
                                        {{ $row['latest']->finished_at?->diffForHumans() ?? 'running…' }}
                                        @if ($row['latest']->summary)
                                            <div class="text-muted">{{ \Illuminate\Support\Str::limit($row['latest']->summary, 90) }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">Never</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $badge }} rounded-pill">{{ $status ?? 'never run' }}</span></td>
                                <td class="small text-nowrap">
                                    {{ $row['next_run_at'] ? $row['next_run_at']->format('d M H:i') : '—' }}
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.crons.run', $row['key']) }}" data-confirm="Run “{{ $row['title'] }}” now?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-dark rounded-pill">Run now</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Run history --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4" data-animate>
        <div class="card-body p-4">
            <h2 class="h6 fw-bold mb-3">Run history <span class="text-muted fw-normal small">(latest 50)</span></h2>
            @if ($history->isEmpty())
                <p class="text-muted small mb-0">No runs recorded yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="text-muted small">
                            <tr><th>Job</th><th>Started</th><th>Duration</th><th>Status</th><th>Summary</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $run)
                                @php
                                    $badge = match ($run->status) {
                                        'ok' => 'bg-success',
                                        'warning' => 'bg-warning text-dark',
                                        'failed' => 'bg-danger',
                                        default => 'bg-info text-dark',
                                    };
                                @endphp
                                <tr>
                                    <td class="small fw-semibold">{{ $run->job_key }}</td>
                                    <td class="small text-nowrap">{{ $run->started_at?->format('d M H:i:s') }}</td>
                                    <td class="small text-nowrap">{{ $run->duration_ms !== null ? number_format($run->duration_ms) . ' ms' : '—' }}</td>
                                    <td><span class="badge {{ $badge }} rounded-pill">{{ $run->status }}</span></td>
                                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit($run->summary ?? '—', 120) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Manual fallback note --}}
    <div class="card shadow-sm border-0 rounded-4" data-animate>
        <div class="card-body p-4">
            <h2 class="h6 fw-bold mb-2">🛟 No system cron on this server?</h2>
            <p class="small text-muted mb-0">
                Use the <strong>Run now</strong> buttons above to trigger any job by hand, or ask your host
                to add the crontab line shown in the heartbeat card. The heartbeat card turns green once
                <code>schedule:run</code> starts ticking every minute.
            </p>
        </div>
    </div>
</div>
@endsection
