<?php

namespace App\Services;

use App\Models\AdImpression;
use App\Models\AdPlacement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Ad rendering engine.
 *
 * Views ask for a *slot* (e.g. "dashboard-banner"); the service finds every
 * placement registered for that slot, keeps only the ones eligible for the
 * current device + page + session frequency cap, picks one by weighted
 * priority rotation, logs an impression and returns the placement's
 * custom code. When nothing is eligible it returns an empty string so the
 * layout never breaks.
 *
 * Trust model: custom_code is pasted by an admin and rendered as-is
 * (admin-pasted JS runs by design — that is the product's purpose). Only
 * admin accounts can edit it, so treat it with the same trust as admin
 * panel access itself.
 */
class AdService
{
    public const SESSION_CAP_KEY = 'ad_caps';

    /**
     * 'mobile' when the user agent looks like a phone/tablet, else 'desktop'.
     */
    public function detectDevice(?string $userAgent): string
    {
        $ua = strtolower($userAgent ?? '');

        foreach (['mobile', 'android', 'iphone', 'ipad', 'phone'] as $needle) {
            if (str_contains($ua, $needle)) {
                return AdPlacement::DEVICE_MOBILE;
            }
        }

        return AdPlacement::DEVICE_DESKTOP;
    }

    /**
     * Render a slot for the current request. Returns '' when ineligible.
     */
    public function renderSlot(
        string $slot,
        string $page,
        ?User $user,
        Request $request,
        ?float $roll = null
    ): string {
        $device = $this->detectDevice($request->userAgent());

        $placement = $this->selectPlacement(
            $this->eligiblePlacements($slot, $page, $device, $request),
            $roll
        );

        if ($placement === null) {
            return '';
        }

        $this->recordImpression($placement, $user, $request, $page, $device, false);

        $code = (string) ($placement->custom_code ?? '');

        if (trim($code) === '') {
            return '';
        }

        return '<div class="ep-ad" data-ad-slot="' . e($slot) . '"'
            . ' data-ad-placement="' . e($placement->slug) . '">'
            . $code
            . '</div>';
    }

    /**
     * All placements for the slot that may serve right now.
     *
     * @return Collection<int, AdPlacement>
     */
    public function eligiblePlacements(
        string $slot,
        string $page,
        string $device,
        Request $request
    ): Collection {
        $caps = $this->sessionCaps($request);

        return AdPlacement::with('network')
            ->where('slot', $slot)
            ->where('enabled', true)
            ->whereHas('network', fn ($q) => $q->where('enabled', true))
            ->get()
            ->filter(function (AdPlacement $placement) use ($page, $device, $caps) {
                if ($placement->device !== AdPlacement::DEVICE_ALL
                    && $placement->device !== $device
                ) {
                    return false;
                }

                $pages = $placement->pages;
                if (is_array($pages) && $pages !== [] && ! in_array($page, $pages, true)) {
                    return false;
                }

                $shown = $caps[$placement->id] ?? 0;

                return $shown < max(1, $placement->frequency_cap_per_session);
            })
            ->values();
    }

    /**
     * Weighted rotation by priority. $roll in [0,1) makes the pick
     * deterministic (used by tests); otherwise a random draw.
     */
    public function selectPlacement(Collection $placements, ?float $roll = null): ?AdPlacement
    {
        $placements = $placements->values();

        if ($placements->isEmpty()) {
            return null;
        }

        $total = (int) $placements->sum('priority');

        if ($total <= 0) {
            return $placements->first();
        }

        $draw = ($roll ?? (random_int(0, PHP_INT_MAX) / PHP_INT_MAX)) * $total;

        foreach ($placements as $placement) {
            $draw -= (int) $placement->priority;

            if ($draw < 0) {
                return $placement;
            }
        }

        return $placements->last();
    }

    public function recordImpression(
        AdPlacement $placement,
        ?User $user,
        Request $request,
        string $page,
        string $device,
        bool $rewarded
    ): void {
        AdImpression::create([
            'placement_id' => $placement->id,
            'user_id' => $user?->id,
            'ip' => $request->ip(),
            'device' => $device,
            'page' => $page,
            'rewarded' => $rewarded,
        ]);

        $caps = $this->sessionCaps($request);
        $caps[$placement->id] = ($caps[$placement->id] ?? 0) + 1;

        // Session caps are best-effort: requests without a session
        // (console, Blade::render outside HTTP) simply skip the cap tracking.
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_CAP_KEY, $caps);
        }
    }

    /**
     * The rewarded placement to offer on a page (weighted rotation among
     * eligible rewarded placements for the "rewarded" slot).
     */
    public function rewardedPlacement(string $page, Request $request, ?float $roll = null): ?AdPlacement
    {
        $device = $this->detectDevice($request->userAgent());

        $candidates = $this->eligiblePlacements('rewarded', $page, $device, $request)
            ->filter(fn (AdPlacement $p) => $p->isRewarded())
            ->values();

        return $this->selectPlacement($candidates, $roll);
    }

    /**
     * @return array<int, int> placement_id => impressions shown this session
     */
    public function sessionCaps(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $caps = $request->session()->get(self::SESSION_CAP_KEY, []);

        return is_array($caps) ? $caps : [];
    }
}
