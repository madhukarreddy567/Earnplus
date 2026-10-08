<?php $title = 'Earn tasks'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 pt-3">
    @if (session('success'))
        <div class="alert alert-success border-0 rounded-4" data-animate>
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger border-0 rounded-4" data-animate>
            {{ session('error') }}
        </div>
    @endif

    <h1 class="h4 fw-bold mb-1" data-animate>Earn tasks</h1>
    <p class="text-muted small mb-3" data-animate data-delay="80">Complete offers from our partners and earn coins for every one.</p>

    {{-- Festival promotion banner (live countdown) --}}
    <x-promo-banner :promotion="$promoBanner" />

    {{-- Demo sandbox tasks --}}
    @if ($demo)
        <div class="ep-section-title mt-4" data-animate>
            <span>{{ $demo->name }}</span>
            <span class="badge rounded-pill" style="background:var(--ep-primary-soft);color:var(--ep-primary-dark);">Sandbox</span>
            @if ($demoBadge)
                <span class="ep-qa-badge ep-qa-badge-promo position-static">{{ $demoBadge }}</span>
            @endif
        </div>
        <div class="d-grid gap-3 mb-4">
            @foreach ($demoTasks as $i => $task)
                <div class="ep-provider-card" data-animate data-delay="{{ $i * 80 }}">
                    <div class="ep-provider-logo">{{ mb_substr($task['kind'], 0, 1) }}</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">{{ $task['title'] }}</div>
                        <div class="text-muted small">{{ $task['description'] }}</div>
                        <div class="ep-payout mt-1">+<span data-countup="{{ $task['payout_coins'] }}">0</span> 🪙</div>
                    </div>
                    <form method="POST" action="{{ route('tasks.demo.complete', $task['key']) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Do it</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Native ad slot (renders only when a placement is eligible) --}}
    <div class="mb-4" data-animate>
        <x-ad placement="tasks-native" />
    </div>

    {{-- Rewarded ad: watch & earn --}}
    @if ($rewardedPlacement)
        <div id="bonus-ads" class="ep-section-title" data-animate>
            <span>Bonus ads</span>
            <span class="badge rounded-pill" style="background:#fff4d6;color:#8a5a00;">Earn more</span>
        </div>
        <div class="mb-4">
            @include('ads.reward-card', ['placement' => $rewardedPlacement, 'countdown' => $rewardCountdown])
        </div>
    @endif

    {{-- Real offerwall providers --}}
    <div class="ep-section-title" data-animate>
        <span>Offer partners</span>
    </div>
    @if ($providers->isEmpty())
        <div class="ep-card" data-animate data-delay="100">
            <div class="ep-empty">No offer partners are live right now — check back soon.</div>
        </div>
    @else
        <div class="d-grid gap-3 mb-4">
            @foreach ($providers as $i => $provider)
                <div class="ep-provider-card" data-animate data-delay="{{ $i * 80 }}">
                    <div class="ep-provider-logo" data-float>{{ mb_substr($provider->name, 0, 1) }}</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">
                            {{ $provider->name }}
                            @if (! empty($providerBadges[$provider->id]))
                                <span class="ep-qa-badge ep-qa-badge-promo position-static">{{ $providerBadges[$provider->id] }}</span>
                            @endif
                        </div>
                        <div class="text-muted small">{{ $provider->config['tagline'] ?? 'Offers, surveys and more' }}</div>
                        <div class="small text-muted mt-1">
                            @if (! empty($provider->config['max_payout_coins']))
                                Earn up to <span class="ep-payout">{{ number_format($provider->config['max_payout_coins']) }}</span> 🪙
                            @else
                                Earn coins 🪙
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('tasks.out', $provider) }}" class="btn btn-dark btn-sm rounded-pill px-3">Open</a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
