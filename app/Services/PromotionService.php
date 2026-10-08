<?php

namespace App\Services;

use App\Models\Promotion;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Date-based promotions with coin multipliers.
 *
 * Every earning path asks this service for the final coin amount:
 * when no promotion is active the base amount comes back untouched,
 * so the no-promotion path is byte-for-byte identical to before.
 */
class PromotionService
{
    /**
     * Promotions live right now (or at $at) that apply to the given
     * earning scope, ordered by priority then id.
     *
     * A 'global' promotion applies to every scope. A scoped promotion
     * applies only to its scope; when it names a provider it applies
     * only to that provider's conversions.
     */
    public function activePromotions(string $scope, ?int $providerId = null, CarbonInterface|string|null $at = null): Collection
    {
        // The promotions module toggle kills every multiplier instantly.
        if (! setting_bool('promotions_enabled', true)) {
            return collect();
        }

        $at = $at ? \Carbon\Carbon::parse($at) : now();

        return Promotion::query()
            ->where('enabled', true)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at)
            ->where(function ($q) use ($scope, $providerId) {
                $q->where('scope', Promotion::SCOPE_GLOBAL)
                    ->orWhere(function ($q2) use ($scope, $providerId) {
                        $q2->where('scope', $scope)
                            ->where(function ($q3) use ($providerId) {
                                if ($providerId === null) {
                                    $q3->whereNull('provider_id');
                                } else {
                                    $q3->whereNull('provider_id')
                                        ->orWhere('provider_id', $providerId);
                                }
                            });
                    });
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * Apply live promotions to a base coin amount.
     *
     * Stacking (setting promotion_stacking):
     * - best_only (default): the highest multiplier wins.
     * - multiply: all multipliers stack multiplicatively, capped by
     *   promotion_max_stacked_multiplier (default 5.0).
     *
     * Coins are integers: multiply, then round DOWN — fractions are
     * never paid.
     *
     * @return array{coins: int, promotions: Collection<int, Promotion>}
     */
    public function applyMultipliers(
        int $baseCoins,
        string $scope,
        ?int $providerId = null,
        CarbonInterface|string|null $at = null
    ): array {
        $promotions = $this->activePromotions($scope, $providerId, $at);

        if ($promotions->isEmpty() || $baseCoins <= 0) {
            return ['coins' => $baseCoins, 'promotions' => $promotions];
        }

        if (setting('promotion_stacking', 'best_only') === 'multiply') {
            $product = 1.0;
            foreach ($promotions as $promotion) {
                $product *= (float) $promotion->multiplier;
            }
            $cap = max(1.0, (float) setting('promotion_max_stacked_multiplier', 5.0));
            $product = min($product, $cap);
            $final = (int) floor($baseCoins * $product);
        } else {
            $best = 1.0;
            foreach ($promotions as $promotion) {
                $best = max($best, (float) $promotion->multiplier);
            }
            $final = (int) floor($baseCoins * $best);
        }

        return ['coins' => max($baseCoins, $final), 'promotions' => $promotions];
    }

    /**
     * The single promotion to feature in a banner for a scope:
     * highest priority among the live ones.
     */
    public function bannerFor(string $scope, ?int $providerId = null): ?Promotion
    {
        /** @var Promotion|null $promo */
        $promo = $this->activePromotions($scope, $providerId)->first();

        return $promo;
    }

    /**
     * Badge text (e.g. "2X") for a UI tile, or null when nothing
     * is active for the scope. Shows the strongest live multiplier.
     */
    public function badgeFor(string $scope, ?int $providerId = null): ?string
    {
        $best = $this->activePromotions($scope, $providerId)
            ->sortByDesc(fn (Promotion $p) => (float) $p->multiplier)
            ->first();

        return $best?->badgeText();
    }

    /**
     * Promotion ids to record in a coin transaction's meta.
     *
     * @param Collection<int, Promotion> $promotions
     * @return list<int>
     */
    public function promotionIds(Collection $promotions): array
    {
        return $promotions->pluck('id')->all();
    }
}
