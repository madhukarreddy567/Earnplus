{{--
  Rewarded-ad card ("Watch ad & earn").
  Expects: $placement (AdPlacement, rewarded), $countdown (seconds).
  The countdown is cosmetic — the server re-verifies everything on claim.
--}}
<div class="ep-card" data-reward-card data-animate data-delay="120"
    data-claim-url="{{ route('ads.reward', $placement) }}"
    data-countdown="{{ $countdown }}"
    data-coins="{{ $placement->coins }}">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="ep-qa-icon mb-0" style="width:44px;height:44px;">🎬</span>
        <h2 class="h6 fw-bold mb-0">Watch ad &amp; earn</h2>
        <span class="badge rounded-pill ms-auto" style="background:var(--ep-primary-soft);color:var(--ep-primary-dark);">+{{ $placement->coins }} 🪙</span>
    </div>

    {{-- Step 1: start --}}
    <div data-reward-step="start">
        <p class="text-muted small mb-3">Watch a short ad, then claim your coins. No catch — the timer is on us.</p>
        <button type="button" class="btn btn-primary w-100 py-2 fw-bold" data-reward-start>▶&nbsp; Watch ad</button>
    </div>

    {{-- Step 2: watching (ad creative + countdown) --}}
    <div data-reward-step="watch" class="d-none">
        <div class="rounded-4 overflow-hidden mb-3" style="border-radius:16px;overflow:hidden;">
            {!! $placement->custom_code !!}
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="ep-countdown-track flex-grow-1">
                <div class="ep-countdown-fill" data-reward-progress></div>
            </div>
            <span class="fw-bold text-muted small" data-reward-seconds>{{ $countdown }}s</span>
        </div>
        <p class="text-muted small mt-2 mb-0">Keep this open until the timer finishes…</p>
    </div>

    {{-- Step 3: claim --}}
    <div data-reward-step="claim" class="d-none">
        <p class="text-success fw-semibold mb-3">🎉 Ad finished — your coins are ready!</p>
        <button type="button" class="btn btn-primary w-100 py-2 fw-bold" data-reward-claim>Claim +{{ $placement->coins }} coins</button>
    </div>

    {{-- Step 4: done / error --}}
    <div data-reward-step="done" class="d-none text-center py-2">
        <div class="fs-1 mb-2" data-reward-burst>🪙</div>
        <p class="fw-bold mb-1">+<span data-reward-earned>0</span> coins added!</p>
        <p class="text-muted small mb-0">Wallet balance: <strong data-reward-balance>0</strong> 🪙</p>
    </div>
    <div data-reward-step="error" class="d-none">
        <div class="alert alert-warning border-0 rounded-4 mb-0 small" data-reward-error></div>
    </div>
</div>
