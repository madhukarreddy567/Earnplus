<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Get a DB-backed setting value by key.
     *
     * Every toggle and configurable value in EarnPlus lives in the
     * settings table — nothing is hardcoded. Values are cached.
     *
     * @param  string  $key
     * @param  mixed  $default  Returned when the key does not exist.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('setting_bool')) {
    /**
     * Get a DB-backed setting as boolean.
     */
    function setting_bool(string $key, bool $default = false): bool
    {
        return Setting::boolean($key, $default);
    }
}

if (! function_exists('setting_int')) {
    /**
     * Get a DB-backed setting as integer.
     */
    function setting_int(string $key, int $default = 0): int
    {
        return Setting::integer($key, $default);
    }
}

if (! function_exists('coins_to_rupees')) {
    /**
     * Convert coins to rupees using the admin-editable
     * coins_per_rupee setting (default: 100 coins = ₹1).
     */
    function coins_to_rupees(int $coins): float
    {
        $perRupee = setting_int('coins_per_rupee', 100);

        if ($perRupee <= 0) {
            $perRupee = 100;
        }

        return round($coins / $perRupee, 2);
    }
}

if (! function_exists('format_rupees')) {
    /**
     * Format a rupee value for display (e.g. ₹12.50).
     */
    function format_rupees(float $rupees): string
    {
        return '₹' . number_format($rupees, 2);
    }
}

if (! function_exists('google_auth_enabled')) {
    /**
     * "Continue with Google" is available only when the owner has pasted
     * Google OAuth credentials (client ID + secret) into the settings.
     * Empty credentials = the feature is inert and buttons hide.
     */
    function google_auth_enabled(): bool
    {
        return setting('google_client_id', '') !== ''
            && setting('google_client_secret', '') !== '';
    }
}

if (! function_exists('render_ad')) {
    /**
     * Render an ad slot (e.g. "dashboard-banner"). Picks the best eligible
     * placement for the current device + page, logs an impression and
     * returns its code. Returns '' when nothing is eligible — the layout
     * never breaks.
     */
    function render_ad(string $slot, ?string $page = null): string
    {
        $request = request();
        $service = app(\App\Services\AdService::class);

        $page = $page ?? $request->route()?->getName() ?? $request->path();

        return $service->renderSlot($slot, (string) $page, $request->user(), $request);
    }
}

if (! function_exists('branding_url')) {
    /**
     * Absolute public URL for a branding asset setting key (e.g.
     * "branding_logo"). Null when nothing is uploaded — views render
     * their built-in default instead.
     */
    function branding_url(string $settingKey): ?string
    {
        return app(\App\Services\BrandingService::class)->url($settingKey);
    }
}

if (! function_exists('design_color')) {
    /**
     * A design color setting, guaranteed to be a valid #rrggbb hex.
     * Falls back to $default when the stored value is malformed —
     * the layout can never emit broken CSS.
     */
    function design_color(string $key, string $default): string
    {
        $value = strtolower(trim((string) setting($key, $default)));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : $default;
    }
}

if (! function_exists('shade_color')) {
    /**
     * Darken (negative $percent) or lighten (positive $percent) a
     * #rrggbb hex color. Used to derive --ep-primary-dark / -deep
     * from the admin's chosen primary color.
     */
    function shade_color(string $hex, float $percent): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#' . $hex;
        }

        $out = '#';

        foreach ([0, 2, 4] as $i) {
            $channel = hexdec(substr($hex, $i, 2));

            $channel = $percent < 0
                ? (int) round($channel * (1 + $percent / 100))
                : (int) round($channel + (255 - $channel) * ($percent / 100));

            $out .= str_pad(dechex(max(0, min(255, $channel))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }
}

if (! function_exists('csp_nonce')) {
    /**
     * Phase 10: the per-request Content-Security-Policy nonce emitted by
     * the SecurityHeaders middleware. Inline scripts must carry it:
     * <script nonce="{{ csp_nonce() }}">…</script>
     */
    function csp_nonce(): string
    {
        if (app()->bound('csp_nonce')) {
            return (string) app('csp_nonce');
        }

        return (string) request()->attributes->get('csp_nonce', '');
    }
}
