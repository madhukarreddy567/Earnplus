<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Site settings (Phase 9): general, feature/module toggles and
 * maintenance mode. Everything lives in the settings table —
 * changes apply instantly, no deploy needed.
 */
class SettingsController extends Controller
{
    /**
     * Settings the page may write, grouped by tab.
     *
     * @var array<string, array<string, string>>
     */
    protected const TABS = [
        'general' => [
            'site_name' => 'branding',
            'site_tagline' => 'branding',
            'support_email' => 'general',
            'default_currency' => 'general',
            'default_language' => 'general',
        ],
        'features' => [
            'otp_enabled' => 'features',
            'recaptcha_enabled' => 'features',
            'email_auth_enabled' => 'features',
            'offerwalls_enabled' => 'features',
            'ads_enabled' => 'features',
            'spin_enabled' => 'features',
            'daily_checkin_enabled' => 'features',
            'referral_enabled' => 'features',
            'promotions_enabled' => 'features',
            'withdrawals_enabled' => 'features',
        ],
        'maintenance' => [
            'maintenance_mode' => 'features',
            'maintenance_message' => 'general',
        ],
        'spin' => [
            'spin_min_coins' => 'spin',
            'spin_max_coins' => 'spin',
            'spin_daily_limit' => 'spin',
            'spin_win_probability' => 'spin',
            'spin_ad_gate_enabled' => 'spin',
            'spin_claim_expiry_minutes' => 'spin',
        ],
    ];

    /** @var array<string, string> */
    protected const LABELS = [
        'site_name' => 'Site name',
        'site_tagline' => 'Tagline',
        'support_email' => 'Support e-mail',
        'default_currency' => 'Default currency',
        'default_language' => 'Default language',
        'otp_enabled' => 'Mobile OTP at login',
        'recaptcha_enabled' => 'reCAPTCHA on auth forms',
        'email_auth_enabled' => 'E-mail signup / sign-in (default OFF — Google-only)',
        'offerwalls_enabled' => 'Offerwalls / task modules',
        'ads_enabled' => 'Ad placements & rewarded ads',
        'spin_enabled' => 'Spin wheel',
        'daily_checkin_enabled' => 'Daily check-in',
        'referral_enabled' => 'Referral program',
        'promotions_enabled' => 'Promotion multipliers',
        'withdrawals_enabled' => 'Withdrawals',
        'maintenance_mode' => 'Maintenance mode',
        'maintenance_message' => 'Maintenance message',
        'spin_min_coins' => 'Minimum coins per spin',
        'spin_max_coins' => 'Maximum coins per spin',
        'spin_daily_limit' => 'Free spins per day',
        'spin_win_probability' => 'Win probability (%)',
        'spin_ad_gate_enabled' => 'Require Unity ad to claim spin reward',
        'spin_claim_expiry_minutes' => 'Claim token expiry (minutes)',
    ];

    /** @var array<string, string> */
    protected const HINTS = [
        'email_auth_enabled' => 'When OFF, /register, /login (e-mail), /forgot-password return 404 and all e-mail forms disappear from the UI. Google sign-in is unaffected.',
        'maintenance_mode' => 'When ON, every visitor sees the maintenance page — except signed-in admins.',
        'offerwalls_enabled' => 'When OFF, /tasks and task links stop working.',
        'ads_enabled' => 'When OFF, ad slots render nothing and rewarded claims are rejected.',
        'promotions_enabled' => 'When OFF, all festival multipliers are ignored.',
        'spin_ad_gate_enabled' => 'When ON, a spin win mints a claim token and the wallet is credited only after Unity\u2019s server-to-server callback verifies the rewarded-ad view. Turn OFF only for development/testing without Unity ads.',
        'spin_claim_expiry_minutes' => 'Pending spin claims expire after this long; expired claims can never be credited.',
    ];

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'general');

        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'general';
        }

        $values = [];

        foreach (self::TABS[$tab] as $key => $group) {
            $values[$key] = setting($key, $this->defaultFor($key));
        }

        return view('admin.settings.index', [
            'tab' => $tab,
            'tabs' => array_keys(self::TABS),
            'keys' => self::TABS[$tab],
            'labels' => self::LABELS,
            'hints' => self::HINTS,
            'values' => $values,
        ]);
    }

    /**
     * Save one tab of settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $tab = (string) $request->input('tab', 'general');

        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'general';
        }

        $rules = $this->rulesFor($tab);
        $validated = $request->validate($rules);

        foreach (self::TABS[$tab] as $key => $group) {
            if (str_ends_with($key, '_enabled') || $key === 'maintenance_mode') {
                // Checkboxes: present = on, absent = off.
                Setting::set($key, $request->boolean($key) ? '1' : '0', $group);
            } elseif (array_key_exists($key, $validated)) {
                Setting::set($key, $validated[$key], $group);
            }
        }

        return redirect()
            ->route('admin.settings.index', ['tab' => $tab])
            ->with('status', 'Settings saved.');
    }

    /**
     * Validation rules per tab.
     */
    protected function rulesFor(string $tab): array
    {
        return match ($tab) {
            'general' => [
                'site_name' => ['required', 'string', 'max:60'],
                'site_tagline' => ['nullable', 'string', 'max:120'],
                'support_email' => ['nullable', 'email', 'max:120'],
                'default_currency' => ['required', 'in:INR,USD'],
                'default_language' => ['required', 'in:en,hi,te'],
            ],
            'features' => [],
            'maintenance' => [
                'maintenance_message' => ['nullable', 'string', 'max:500'],
            ],
            'spin' => [
                'spin_min_coins' => ['required', 'integer', 'min:1', 'max:100000'],
                'spin_max_coins' => ['required', 'integer', 'min:1', 'max:100000'],
                'spin_daily_limit' => ['required', 'integer', 'min:1', 'max:100'],
                'spin_win_probability' => ['required', 'integer', 'min:0', 'max:100'],
                'spin_claim_expiry_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            ],
            default => [],
        };
    }

    /**
     * The value used when a setting has never been stored.
     */
    protected function defaultFor(string $key): string
    {
        return match ($key) {
            'site_name' => 'EarnPlus',
            'site_tagline' => 'Earn coins. Redeem rewards.',
            'support_email' => '',
            'default_currency' => 'INR',
            'default_language' => 'en',
            'maintenance_message' => 'We are doing scheduled maintenance. Please check back soon.',
            'spin_min_coins' => '5',
            'spin_max_coins' => '100',
            'spin_daily_limit' => '1',
            'spin_win_probability' => '40',
            'spin_ad_gate_enabled' => '1',
            'spin_claim_expiry_minutes' => '15',
            default => str_ends_with($key, '_enabled') || $key === 'maintenance_mode' ? '0' : '',
        };
    }
}
