<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\LoginLog;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Services\IpBlockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Phase 10: the admin security center.
 *
 * - Login attempt log (both guards).
 * - Security events (blocked IPs, rate-limit hits, lockdowns).
 * - IP blocklist management (single IP or CIDR, optional expiry).
 * - Proxy / header settings (Cloudflare mode, whitelist, HSTS).
 * - Emergency lockdown: maintenance mode ON + every session destroyed.
 */
class SecurityController extends Controller
{
    public function index(): View
    {
        return view('admin.security.index', [
            'loginLogs' => LoginLog::query()->latest()->limit(50)->get(),
            'failedLogins' => LoginLog::query()->where('success', false)->latest()->limit(50)->get(),
            'events' => SecurityEvent::query()->latest()->limit(50)->get(),
            'blocks' => BlockedIp::query()->latest()->get(),
            'settings' => [
                'trust_proxy_headers' => setting_bool('trust_proxy_headers', false),
                'trusted_proxy_cidrs' => setting('trusted_proxy_cidrs', ''),
                'security_ip_whitelist' => setting('security_ip_whitelist', ''),
                'hsts_enabled' => setting_bool('hsts_enabled', false),
            ],
        ]);
    }

    public function storeBlock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_or_cidr' => ['required', 'string', 'max:45'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $value = trim($validated['ip_or_cidr']);

        if (! IpBlockService::isValidIpOrCidr($value)) {
            return back()->withErrors(['ip_or_cidr' => 'Enter a valid IPv4/IPv6 address or CIDR range (e.g. 1.2.3.4 or 1.2.3.0/24).'])->withInput();
        }

        // Safety: never let an admin block their own current IP by accident.
        if (\Symfony\Component\HttpFoundation\IpUtils::checkIp($request->ip(), $value)) {
            return back()->withErrors(['ip_or_cidr' => 'That range includes your own current IP — blocking it would lock you out.'])->withInput();
        }

        BlockedIp::updateOrCreate(
            ['ip_or_cidr' => $value],
            [
                'reason' => $validated['reason'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'created_by_admin_id' => Auth::guard('admin')->id(),
            ]
        );

        return back()->with('success', "Blocked {$value}.");
    }

    public function destroyBlock(BlockedIp $blockedIp): RedirectResponse
    {
        $blockedIp->delete();

        return back()->with('success', "Unblocked {$blockedIp->ip_or_cidr}.");
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trust_proxy_headers' => ['nullable', 'boolean'],
            'trusted_proxy_cidrs' => ['nullable', 'string', 'max:4000'],
            'security_ip_whitelist' => ['nullable', 'string', 'max:4000'],
            'hsts_enabled' => ['nullable', 'boolean'],
        ]);

        // Every configured CIDR must be valid — one bad line breaks the
        // whole trust chain, so reject the save instead.
        foreach (preg_split('/[\r\n,]+/', (string) ($validated['trusted_proxy_cidrs'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '' && ! IpBlockService::isValidIpOrCidr($line)) {
                return back()->withErrors(['trusted_proxy_cidrs' => "Invalid IP or CIDR: {$line}"])->withInput();
            }
        }

        foreach (preg_split('/[\r\n,]+/', (string) ($validated['security_ip_whitelist'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '' && ! IpBlockService::isValidIpOrCidr($line)) {
                return back()->withErrors(['security_ip_whitelist' => "Invalid IP or CIDR: {$line}"])->withInput();
            }
        }

        Setting::set('trust_proxy_headers', $request->boolean('trust_proxy_headers') ? '1' : '0', 'security');
        Setting::set('trusted_proxy_cidrs', trim((string) ($validated['trusted_proxy_cidrs'] ?? '')), 'security');
        Setting::set('security_ip_whitelist', trim((string) ($validated['security_ip_whitelist'] ?? '')), 'security');
        Setting::set('hsts_enabled', $request->boolean('hsts_enabled') ? '1' : '0', 'security');

        return back()->with('success', 'Security settings saved.');
    }

    /**
     * Emergency lockdown: maintenance mode ON and every session in the
     * database destroyed (users AND admins, including this one). The admin
     * lands back on the admin login page.
     */
    public function lockdown(Request $request): RedirectResponse
    {
        Setting::set('maintenance_mode', '1', 'features');
        Setting::set('maintenance_message', 'Emergency lockdown — we\'ll be back shortly.', 'features');

        SecurityEvent::record(
            SecurityEvent::TYPE_LOCKDOWN,
            $request->ip(),
            'admin/security/lockdown',
            'Emergency lockdown by admin #' . Auth::guard('admin')->id()
        );

        try {
            DB::table('sessions')->delete();
        } catch (\Throwable) {
            // Non-database session drivers have nothing to delete here.
        }

        Auth::guard('admin')->logout();

        return redirect()->route('admin.login')
            ->with('status', 'Emergency lockdown active: the site is in maintenance mode and all sessions were terminated.');
    }
}
