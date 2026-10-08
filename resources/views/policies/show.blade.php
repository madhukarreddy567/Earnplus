<?php $title = $page->title; ?>
@extends('layouts.app')

@section('head')
    <meta name="description" content="{{ $page->title }} — {{ setting('site_name', 'EarnPlus') }}">
    <meta property="og:title" content="{{ $page->title }} — {{ setting('site_name', 'EarnPlus') }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('policies.show', $page->slug) }}">
@endsection

@section('content')
<div class="px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width:720px;" data-animate>
        <h1 class="h4 fw-bold mb-1">{{ $page->title }}</h1>
        <p class="text-muted small mb-4">Last updated {{ $page->published_at?->format('F j, Y') }}</p>
        <div class="ep-card ep-policy-body">
            {!! $page->body_html !!}
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            @foreach (\App\Models\PolicyPage::SLUGS as $slug)
                @if ($slug !== $page->slug)
                    <a href="{{ route('policies.show', $slug) }}" class="btn btn-sm btn-outline-secondary rounded-pill">{{ \App\Models\PolicyPage::TITLES[$slug] }}</a>
                @endif
            @endforeach
        </div>
    </div>
</div>
@endsection
