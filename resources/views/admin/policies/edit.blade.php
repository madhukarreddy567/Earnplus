<?php $title = 'Edit: ' . $policy->title; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mb-4" data-animate>
        <a href="{{ route('admin.policies.index') }}" class="text-muted small text-decoration-none">← Policy pages</a>
        <h1 class="h4 fw-bold mb-0">{{ $policy->title }}</h1>
        <p class="text-muted small mb-0">Version {{ $policy->version }} · {{ $policy->is_published ? 'Published ' . $policy->published_at?->diffForHumans() : 'Draft (not visible publicly)' }}</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm rounded-4" data-animate>{{ session('status') }}</div>
    @endif
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

    <form method="POST" action="{{ route('admin.policies.update', $policy) }}" id="policy-form" data-animate>
        @csrf
        @method('PUT')
        <div class="ep-card mb-4">
            <label class="form-label small fw-bold" for="title">Page title</label>
            <input type="text" class="form-control mb-3" id="title" name="title" value="{{ old('title', $policy->title) }}" maxlength="120" required>

            <label class="form-label small fw-bold">Content</label>
            <div class="border rounded-3 overflow-hidden">
                <div class="d-flex flex-wrap gap-1 p-2 border-bottom bg-light" id="ep-editor-toolbar" role="toolbar" aria-label="Formatting">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="bold" title="Bold"><strong>B</strong></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="italic" title="Italic"><em>I</em></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="underline" title="Underline"><u>U</u></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="formatBlock" data-value="h2" title="Heading">H2</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="formatBlock" data-value="h3" title="Subheading">H3</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="formatBlock" data-value="blockquote" title="Quote">❝ Quote</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="ep-link-btn" title="Link">🔗 Link</button>
                </div>
                <div id="ep-editor" contenteditable="true" class="p-3" style="min-height:320px;outline:none;">{!! old('body_html', $policy->body_html ?? '') !!}</div>
            </div>
            <input type="hidden" name="body_html" id="body_html">
            <div class="form-text">Formatted text only — scripts and unsafe markup are stripped automatically on save.</div>

            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" role="switch" id="publish" name="publish" value="1" {{ old('publish', $policy->is_published) ? 'checked' : '' }}>
                <label class="form-check-label fw-bold small" for="publish">Publish publicly</label>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-dark rounded-pill px-4">💾 Save as new version</button>
            </div>
        </div>
    </form>

    <div class="ep-card" data-animate>
        <h2 class="h6 fw-bold mb-3">🕘 Version history</h2>
        @if ($revisions->isEmpty())
            <p class="text-muted small mb-0">No earlier versions yet — every save is archived here.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="text-muted small">
                        <tr><th>Version</th><th>Was published</th><th>Saved</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($revisions as $rev)
                            <tr>
                                <td class="fw-bold">v{{ $rev->version }}</td>
                                <td>{{ $rev->was_published ? 'Yes' : 'No' }}</td>
                                <td class="text-muted small">{{ $rev->created_at->diffForHumans() }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.policies.restore', [$policy, $rev->id]) }}" class="d-inline" data-confirm="Restore version {{ $rev->version }} as a new version?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-dark rounded-pill">Restore</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function () {
    var editor = document.getElementById('ep-editor');
    var hidden = document.getElementById('body_html');
    var form = document.getElementById('policy-form');

    document.getElementById('ep-editor-toolbar').addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        if (btn.id === 'ep-link-btn') {
            var url = prompt('Link URL (https://…):', 'https://');
            if (url && /^https?:\/\//i.test(url)) {
                document.execCommand('createLink', false, url);
            }
            editor.focus();
            return;
        }
        document.execCommand(btn.dataset.cmd, false, btn.dataset.value || null);
        editor.focus();
    });

    form.addEventListener('submit', function () {
        hidden.value = editor.innerHTML;
    });
})();
</script>
@endsection
