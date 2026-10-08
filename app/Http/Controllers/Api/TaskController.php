<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfferwallProvider;
use App\Models\Promotion;
use App\Services\ClickVelocityExceededException;
use App\Services\OfferwallService;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offerwall tasks for the mobile app.
 *
 * The app opens the returned wall URL in an in-app WebView; the click
 * is tracked server-side first so postback conversions credit normally.
 */
class TaskController extends Controller
{
    public function __construct(
        protected OfferwallService $offerwalls,
        protected PromotionService $promotions
    ) {
    }

    public function providers(): JsonResponse
    {
        $query = OfferwallProvider::where('enabled', true);

        // Phase 13: the demo sandbox is auto-hidden in production mode.
        if (app(\App\Services\LicenseService::class)->isProduction()) {
            $query->where('sandbox_mode', false);
        }

        $providers = $query
            ->orderByDesc('sandbox_mode')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $providers->map(fn (OfferwallProvider $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'sandbox' => (bool) $p->sandbox_mode,
                'badge' => $this->promotions->badgeFor(
                    Promotion::SCOPE_TASK_OFFERWALL,
                    $p->id
                ),
                'demo_tasks' => $p->sandbox_mode
                    ? collect($p->config['tasks'] ?? [])->map(fn ($t) => [
                        'key' => $t['key'] ?? null,
                        'title' => $t['title'] ?? $t['key'] ?? 'Demo task',
                        'payout_coins' => (int) ($t['payout_coins'] ?? 0),
                    ])->values()
                    : [],
            ])->values(),
        ]);
    }

    /**
     * Track the outbound click and return the wall URL for the WebView.
     */
    public function click(Request $request, OfferwallProvider $provider): JsonResponse
    {
        if (! $provider->enabled) {
            abort(404);
        }

        // Phase 13: the demo sandbox does not exist in production.
        if ($provider->sandbox_mode && app(\App\Services\LicenseService::class)->isProduction()) {
            abort(404);
        }

        try {
            $click = $this->offerwalls->trackClick(
                $provider,
                $request->user(),
                $request->ip(),
                $request->userAgent()
            );
        } catch (ClickVelocityExceededException) {
            return response()->json(
                ['message' => 'Too many task clicks — please slow down and try again later.'],
                429
            );
        }

        $template = $provider->config['offer_url_template'] ?? null;

        if (! is_string($template) || trim($template) === '') {
            return response()->json(
                ['message' => 'This provider is not ready yet — please try another one.'],
                422
            );
        }

        $url = str_replace('{click_uid}', $click->click_uid, $template);
        $url = str_replace('{user_id}', (string) $request->user()->id, $url);

        return response()->json(['url' => $url]);
    }
}
