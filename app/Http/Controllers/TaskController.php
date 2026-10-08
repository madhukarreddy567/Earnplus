<?php

namespace App\Http\Controllers;

use App\Models\OfferwallProvider;
use App\Models\Promotion;
use App\Services\AdService;
use App\Services\ClickVelocityExceededException;
use App\Services\OfferwallService;
use App\Services\PromotionService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Earn tasks page: GET /tasks
 *
 * Lists every enabled offerwall provider as a card. The seeded "Demo"
 * sandbox provider additionally serves 5 sample tasks whose "complete"
 * buttons drive the REAL signed postback endpoint (self-post with a
 * valid HMAC signature), so the whole credit path is genuinely exercised.
 */
class TaskController extends Controller
{
    public function __construct(
        protected OfferwallService $offerwalls,
        protected AdService $ads,
        protected PromotionService $promotions
    ) {
    }

    public function index(Request $request): View
    {
        $query = OfferwallProvider::where('enabled', true);

        // Phase 13: the demo sandbox is auto-hidden in production mode.
        if (app(\App\Services\LicenseService::class)->isProduction()) {
            $query->where('sandbox_mode', false);
        }

        $providers = $query->orderByDesc('sandbox_mode')
            ->orderBy('name')
            ->get();

        $demo = $providers->firstWhere('sandbox_mode', true);
        $demoTasks = $demo !== null ? ($demo->config['tasks'] ?? []) : [];

        $realProviders = $providers->where('sandbox_mode', false)->values();
        $providerBadges = [];
        foreach ($realProviders as $provider) {
            $providerBadges[$provider->id] = $this->promotions->badgeFor(
                Promotion::SCOPE_TASK_OFFERWALL,
                $provider->id
            );
        }

        return view('tasks.index', [
            'providers' => $realProviders,
            'providerBadges' => $providerBadges,
            'demo' => $demo,
            'demoTasks' => $demoTasks,
            'demoBadge' => $demo
                ? $this->promotions->badgeFor(Promotion::SCOPE_TASK_OFFERWALL, $demo->id)
                : null,
            'promoBanner' => $this->promotions->bannerFor(Promotion::SCOPE_TASK_OFFERWALL),
            'rewardedPlacement' => $this->ads->rewardedPlacement('tasks', $request),
            'rewardCountdown' => setting_int('rewarded_ad_countdown_seconds', 15),
        ]);
    }

    /**
     * Demo sandbox: "complete" a sample task. Builds a signed postback
     * and dispatches it through the real /postback/{slug} endpoint via
     * the HTTP kernel — signature, dedupe, click validation and coin
     * credit all run exactly as they would for a real provider.
     */
    public function completeDemo(Request $request, string $task): RedirectResponse
    {
        // Phase 13: the demo sandbox does not exist in production.
        abort_if(app(\App\Services\LicenseService::class)->isProduction(), 404);

        $provider = OfferwallProvider::where('slug', 'demo')
            ->where('enabled', true)
            ->where('sandbox_mode', true)
            ->firstOrFail();

        $tasks = $provider->config['tasks'] ?? [];
        $taskDef = collect($tasks)->firstWhere('key', $task);

        if ($taskDef === null) {
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
            return redirect()->route('tasks')
                ->with('error', 'Too many task clicks — please slow down and try again later.');
        }

        $payload = [
            'provider_tx_id' => 'demo-' . $task . '-' . $click->click_uid,
            'user_id' => $request->user()->id,
            'payout' => (int) $taskDef['payout_coins'],
            'click_uid' => $click->click_uid,
            'task' => $taskDef['key'],
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $rawBody, $provider->postback_secret);

        $subRequest = Request::create(
            '/postback/' . $provider->slug,
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature],
            $rawBody
        );

        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);
        $response = $kernel->handle($subRequest);
        $result = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 200 || ($result['status'] ?? null) !== 'credited') {
            return redirect()->route('tasks')
                ->with('error', 'Demo task could not be completed — please try again.');
        }

        return redirect()->route('tasks')
            ->with('success', "Task completed! {$result['user_coins']} coins were added to your wallet.");
    }
}
