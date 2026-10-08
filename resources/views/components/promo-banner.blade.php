{{--
    Festival promotion banner with a live countdown.
    Usage: <x-promo-banner :promotion="$promoBanner" />
    The countdown end is server-rendered; JS ticks it every second.
--}}
@props(['promotion'])

@if ($promotion)
    <div class="ep-promo-banner mb-3" data-animate>
        <span class="ep-promo-badge" data-animate="pop">{{ $promotion->badgeText() }}</span>
        <div class="ep-promo-text">
            <div class="ep-promo-title">{{ $promotion->banner_title ?: $promotion->name }}</div>
            @if ($promotion->banner_subtitle)
                <div class="ep-promo-sub">{{ $promotion->banner_subtitle }}</div>
            @endif
        </div>
        <div class="ep-promo-countdown" data-countdown-to="{{ $promotion->ends_at->toIso8601String() }}">
            <span class="ep-promo-countdown-label">Ends in</span>
            <span class="ep-promo-countdown-time" aria-live="polite">…</span>
        </div>
    </div>
@endif
