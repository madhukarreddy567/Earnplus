<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenseViolation;
use App\Models\Setting;
use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 13: the admin license page.
 *
 * - License status card (mode, domain, signature, lock state).
 * - Mode toggle + licensed domain / aliases.
 * - Violation log with type + text filters.
 * - Emergency kill switch (super_admin only, double confirmation):
 *   locks every user route instantly; admin login still works to unlock.
 * - Remote kill URL (default off): a signed URL the owner can trigger
 *   from anywhere; the secret is shown only here and is regeneratable.
 */
class LicenseController extends Controller
{
    public function __construct(protected LicenseService $license)
    {
    }

    public function index(Request $request): View
    {
        $violations = LicenseViolation::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $q->where(fn ($qq) => $qq->where('ip', 'like', $term)->orWhere('host', 'like', $term));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.license.index', [
            'status' => $this->license->status(),
            'violations' => $violations,
            'typeLabels' => LicenseViolation::typeLabels(),
            'killSecret' => $this->license->killSecret(),
            'confirmKill' => $request->boolean('confirm_kill'),
            'filters' => $request->only(['type', 'q']),
            'settings' => [
                'app_mode' => $this->license->mode(),
                'licensed_domain' => setting('licensed_domain', ''),
                'license_domain_aliases' => setting('license_domain_aliases', ''),
                'license_violation_block_threshold' => setting('license_violation_block_threshold', '10'),
                'license_remote_kill_enabled' => $this->license->remoteKillEnabled(),
            ],
        ]);
    }

    /**
     * Save mode, licensed domain, aliases, block threshold and the
     * remote-kill toggle.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_mode' => ['required', 'in:development,production'],
            'licensed_domain' => ['nullable', 'string', 'max:255'],
            'license_domain_aliases' => ['nullable', 'string', 'max:1000'],
            'license_violation_block_threshold' => ['required', 'integer', 'min:0', 'max:1000'],
            'license_remote_kill_enabled' => ['nullable', 'boolean'],
        ]);

        $domain = strtolower(trim((string) ($validated['licensed_domain'] ?? '')));
        // Bare hostname only — no scheme, path, port or spaces. Single-label
        // names (localhost, intranet hosts) are allowed for dev installs.
        if ($domain !== '' && ! preg_match('/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)*$/', $domain)) {
            return back()
                ->withErrors(['licensed_domain' => 'Enter a valid domain name, e.g. example.com — no scheme, path or port.'])
                ->withInput();
        }

        // Safety: switching to production with a domain that does not
        // match the current request host would lock the owner out of
        // every user route on the next request. Warn instead of saving
        // blindly.
        if (
            $validated['app_mode'] === LicenseService::MODE_PRODUCTION
            && $domain !== ''
            && ! $this->domainWouldAllow($domain, (string) ($validated['license_domain_aliases'] ?? ''), $request->getHost())
        ) {
            return back()
                ->withErrors(['licensed_domain' => 'That domain does not match the host you are on right now (' . $request->getHost() . '). Saving it would lock you out — double-check the spelling.'])
                ->withInput();
        }

        Setting::set('app_mode', $validated['app_mode'], 'license');
        Setting::set('licensed_domain', $domain, 'license');
        Setting::set('license_domain_aliases', trim((string) ($validated['license_domain_aliases'] ?? '')), 'license');
        Setting::set('license_violation_block_threshold', (string) $validated['license_violation_block_threshold'], 'license');
        Setting::set('license_remote_kill_enabled', $request->boolean('license_remote_kill_enabled') ? '1' : '0', 'license');

        return redirect()->route('admin.license.index')
            ->with('success', 'License settings saved.');
    }

    /**
     * Regenerate the remote-kill secret (invalidates the old URL).
     */
    public function regenerateSecret(): RedirectResponse
    {
        $this->license->regenerateKillSecret();

        return redirect()->route('admin.license.index')
            ->with('success', 'Remote-kill secret regenerated — the old kill URL no longer works.');
    }

    /**
     * Activate the kill switch. Super admin only; the view requires a
     * two-step confirmation before this POST is reachable.
     */
    public function kill(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->role === 'super_admin', 403);

        $request->validate([
            'acknowledge' => ['accepted'],
        ]);

        $this->license->activateKillSwitch('admin kill switch');

        return redirect()->route('admin.license.index')
            ->with('success', 'Kill switch ACTIVE — all user routes now show the lock page.');
    }

    /**
     * Deactivate the kill switch. Super admin only.
     */
    public function unlock(): RedirectResponse
    {
        abort_unless(request()->user('admin')->role === 'super_admin', 403);

        $this->license->deactivateKillSwitch();

        return redirect()->route('admin.license.index')
            ->with('success', 'Kill switch deactivated — the app is serving users again.');
    }

    /**
     * Remote kill URL: GET /license/remote-kill?token=…
     * Disabled unless the owner enabled it on the license page.
     * A bad token gets a plain 404 — the endpoint's purpose is not advertised.
     */
    public function remoteKill(Request $request)
    {
        if (! $this->license->remoteKillEnabled()) {
            abort(404);
        }

        if (! $this->license->remoteKillTokenValid($request->query('token'))) {
            abort(404);
        }

        $this->license->activateKillSwitch('remote kill URL');

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Would the submitted domain + aliases allow the given host?
     * Used to stop the owner from locking themselves out.
     */
    protected function domainWouldAllow(string $domain, string $aliases, string $host): bool
    {
        $normalize = function (string $value): string {
            $value = strtolower(trim($value));
            if (str_contains($value, ':')) {
                $value = explode(':', $value, 2)[0];
            }

            return rtrim($value, '.');
        };

        $domain = $normalize($domain);
        $host = $normalize($host);

        if ($host === $domain || $host === 'www.' . $domain || $domain === 'www.' . $host) {
            return true;
        }

        foreach (preg_split('/[\s,;]+/', $aliases) as $part) {
            if ($normalize(trim($part)) === $host) {
                return true;
            }
        }

        return false;
    }
}
