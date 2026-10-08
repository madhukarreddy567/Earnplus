<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdNetwork;
use Illuminate\Http\JsonResponse;

/**
 * Public app configuration for the Flutter client.
 *
 * Only non-secret values are exposed — API keys, postback secrets and
 * S2S secrets never leave the server. Ad unit / game IDs are public
 * identifiers by design (they ship inside the mobile app anyway).
 */
class ConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $admob = AdNetwork::where('type', AdNetwork::TYPE_ADMOB)
            ->where('enabled', true)->first();
        $unity = AdNetwork::where('type', AdNetwork::TYPE_UNITY_ADS)
            ->where('enabled', true)->first();

        return response()->json([
            'site_name' => setting('site_name', 'EarnPlus'),
            'tagline' => setting('tagline', ''),
            'email_auth_enabled' => setting_bool('email_auth_enabled', false),
            'google_auth_enabled' => google_auth_enabled(),
            // OAuth client IDs are public identifiers (they ship in the
            // web page's Google button flow too) — the app needs them to
            // request ID tokens with the right audience.
            'google_client_id_android' => setting('google_client_id_android', ''),
            'google_client_id_ios' => setting('google_client_id_ios', ''),
            'coins_per_rupee' => setting_int('coins_per_rupee', 100),
            'features' => [
                'spin' => setting_bool('spin_enabled', true),
                'checkin' => setting_bool('daily_checkin_enabled', true),
                'referrals' => setting_bool('referrals_enabled', true),
                'offerwalls' => setting_bool('offerwalls_enabled', true),
                'ads' => setting_bool('ads_enabled', true),
                'promotions' => setting_bool('promotions_enabled', true),
                'withdrawals' => setting_bool('withdrawals_enabled', true),
            ],
            'branding' => [
                'logo' => branding_url('branding_logo'),
                'banner' => branding_url('branding_banner'),
                'banner_1200' => branding_url('branding_banner_1200'),
                'banner_768' => branding_url('branding_banner_768'),
                'avatar' => branding_url('branding_avatar'),
            ],
            'ads' => [
                'admob' => $admob ? [
                    'app_id' => $admob->config['app_id'] ?? null,
                    'rewarded_ad_unit_id' => $admob->config['rewarded_ad_unit_id'] ?? null,
                ] : null,
                'unity' => $unity ? [
                    'game_id_android' => $unity->config['game_id_android'] ?? null,
                    'game_id_ios' => $unity->config['game_id_ios'] ?? null,
                    'rewarded_placement_id' => $unity->config['rewarded_placement_id'] ?? null,
                    'interstitial_placement_id' => $unity->config['interstitial_placement_id'] ?? null,
                ] : null,
            ],
            'withdraw' => [
                'presets_paise' => [1000, 2000, 3000],
                'max_per_day' => setting_int('withdrawal_max_per_day', 3),
            ],
        ]);
    }
}
