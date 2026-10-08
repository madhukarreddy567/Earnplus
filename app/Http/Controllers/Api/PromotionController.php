<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;

/**
 * Active promotions for the mobile app's banners and badges.
 */
class PromotionController extends Controller
{
    public function index(PromotionService $promotions): JsonResponse
    {
        if (! setting_bool('promotions_enabled', true)) {
            return response()->json(['data' => []]);
        }

        $active = $promotions->activePromotions(Promotion::SCOPE_GLOBAL);

        return response()->json([
            'data' => $active->map(fn (Promotion $p) => [
                'name' => $p->name,
                'multiplier' => (float) $p->multiplier,
                'scope' => $p->scope,
                'banner_title' => $p->banner_title,
                'banner_subtitle' => $p->banner_subtitle,
                'badge' => $promotions->badgeFor($p->scope, $p->provider_id),
                'ends_at' => $p->ends_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
