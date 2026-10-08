<?php

namespace App\Http\Controllers;

use App\Models\OfferwallProvider;
use App\Services\ClickVelocityExceededException;
use App\Services\OfferwallService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Outbound click tracking: GET /tasks/out/{provider:slug}
 *
 * Records the click (IP + device fingerprint, velocity-checked) and
 * redirects the user to the provider's offer wall with {click_uid}
 * substituted into the configured URL template.
 */
class OfferwallClickController extends Controller
{
    public function __construct(protected OfferwallService $offerwalls)
    {
    }

    public function redirect(Request $request, OfferwallProvider $provider): RedirectResponse
    {
        if (! $provider->enabled) {
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

        $template = $provider->config['offer_url_template'] ?? null;

        if (! is_string($template) || trim($template) === '') {
            return redirect()->route('tasks')
                ->with('error', 'This provider is not ready yet — please try another one.');
        }

        $url = str_replace('{click_uid}', $click->click_uid, $template);
        $url = str_replace('{user_id}', (string) $request->user()->id, $url);

        return redirect()->away($url);
    }
}
