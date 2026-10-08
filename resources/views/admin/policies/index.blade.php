<?php $title = 'Policy pages'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mb-4" data-animate>
        <h1 class="h4 fw-bold mb-0">📄 Policy pages</h1>
        <p class="text-muted small mb-0">Terms, privacy, refund and about — rich text with full version history. Only published pages are visible publicly.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('status') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('success') }}</div>
    @endif

    <div class="ep-card" data-animate>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="text-muted small">
                    <tr><th>Page</th><th>Version</th><th>Status</th><th>Last updated</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td class="fw-bold">{{ $page->title }}</td>
                            <td>v{{ $page->version }}</td>
                            <td>
                                @if ($page->is_published)
                                    <span class="badge rounded-pill bg-success">Published</span>
                                @else
                                    <span class="badge rounded-pill bg-secondary">Draft</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $page->updated_at?->diffForHumans() }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.policies.edit', $page) }}" class="btn btn-sm btn-dark rounded-pill">Edit</a>
                                @if ($page->is_published)
                                    <a href="{{ route('policies.show', $page->slug) }}" class="btn btn-sm btn-outline-secondary rounded-pill" target="_blank">View live</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
