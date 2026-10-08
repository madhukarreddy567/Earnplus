<?php

namespace Database\Seeders;

use App\Models\AdNetwork;
use App\Models\AdPlacement;
use Illuminate\Database\Seeder;

/**
 * Ad networks: the 4 real networks (all DISABLED — the admin pastes their
 * ad code after approval) plus an enabled "Demo" network with one rewarded
 * placement so the rewarded-ad flow is genuinely exercisable.
 *
 * No real ad accounts exist; nothing here phones home.
 */
class AdSeeder extends Seeder
{
    public function run(): void
    {
        $networks = [
            [
                'name' => 'Google AdSense',
                'slug' => 'adsense',
                'type' => AdNetwork::TYPE_ADSENSE,
                'enabled' => false,
                'config' => [
                    'docs' => 'AdSense → Ads → paste the ad unit code into a placement\'s custom code field.',
                ],
            ],
            [
                'name' => 'Adsterra',
                'slug' => 'adsterra',
                'type' => AdNetwork::TYPE_ADSTERRA,
                'enabled' => false,
                'config' => [
                    'docs' => 'Adsterra → publisher panel → copy the banner/popunder/native code into a placement.',
                ],
            ],
            [
                'name' => 'Monetag',
                'slug' => 'monetag',
                'type' => AdNetwork::TYPE_MONETAG,
                'enabled' => false,
                'config' => [
                    'docs' => 'Monetag → Sites → copy the tag (popunder, vignette, push) into a placement.',
                ],
            ],
            [
                'name' => 'PropellerAds',
                'slug' => 'propellerads',
                'type' => AdNetwork::TYPE_PROPELLERADS,
                'enabled' => false,
                'config' => [
                    'docs' => 'PropellerAds → Sites → copy the zone code into a placement.',
                ],
            ],
            [
                'name' => 'Google AdMob',
                'slug' => 'admob',
                'type' => AdNetwork::TYPE_ADMOB,
                'enabled' => false,
                'config' => [
                    'docs' => 'Mobile-SDK network: the Flutter app renders these ads natively. '
                        . 'Paste your AdMob App ID + rewarded ad unit ID here (see README), '
                        . 'then point AdMob\'s SSV postback at the /ads/verify/admob URL.',
                    'app_id' => '',
                    'rewarded_ad_unit_id' => '',
                    'ssv_key_id' => '',
                ],
            ],
            [
                'name' => 'Unity Ads',
                'slug' => 'unity-ads',
                'type' => AdNetwork::TYPE_UNITY_ADS,
                'enabled' => false,
                'config' => [
                    'docs' => 'Mobile-SDK network: the Flutter app renders these ads natively. '
                        . 'Real IDs are seeded below (Game 800391724); the S2S secret still '
                        . 'needs pasting by the owner. Point Unity\'s server-to-server callback at the '
                        . '/ads/verify/unity-ads URL.',
                    'game_id_android' => '800391724',
                    'game_id_ios' => '',
                    'rewarded_placement_id' => 'BP_Rewarded_Android',
                    'rewarded_ad_unit_id' => 'a22cdead-1375-45a1-a611-10554ac4982c',
                    'banner_placement_id' => 'BP_Banner_Android',
                    'interstitial_placement_id' => 'BP_Interstitial_Android',
                    'interstitial_ad_unit_id' => 'a22cdead-1375-45a1-a611-10554ac4982c',
                    's2s_secret' => '',
                ],
            ],
            [
                'name' => 'Demo',
                'slug' => 'demo',
                'type' => AdNetwork::TYPE_CUSTOM,
                'enabled' => true,
                'config' => [
                    'docs' => 'Built-in placeholder creatives for testing the ad engine end-to-end.',
                ],
            ],
        ];

        foreach ($networks as $row) {
            // Seed-as-defaults: never wipe a value the owner already pasted
            // in the admin panel (e.g. the Unity S2S secret). Existing
            // non-empty config values win; the seeder only fills gaps.
            $network = AdNetwork::firstOrNew(['slug' => $row['slug']]);
            $existing = is_array($network->config) ? $network->config : [];
            $merged = $row['config'];
            foreach ($existing as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $merged[$key] = $value;
                }
            }
            $row['config'] = $merged;
            $network->fill($row)->save();
        }

        $demo = AdNetwork::where('slug', 'demo')->firstOrFail();

        AdPlacement::updateOrCreate(
            ['slug' => 'demo-rewarded'],
            [
                'network_id' => $demo->id,
                'name' => 'Demo rewarded ad',
                'slot' => 'rewarded',
                'enabled' => true,
                'placement_type' => AdPlacement::TYPE_REWARDED,
                'device' => AdPlacement::DEVICE_ALL,
                'pages' => ['tasks', 'dashboard'],
                'frequency_cap_per_session' => 5,
                'priority' => 10,
                'coins' => 5,
                'custom_code' => $this->demoCreative(),
            ]
        );
    }

    /**
     * Styled placeholder creative: pure HTML + inline CSS, no external
     * assets, clearly labelled so nobody mistakes it for a real ad.
     */
    protected function demoCreative(): string
    {
        return <<<'HTML'
<div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);border-radius:16px;padding:28px 24px;text-align:center;color:#fff;position:relative;overflow:hidden;">
  <div style="position:absolute;inset:0;background:linear-gradient(110deg,transparent 30%,rgba(255,255,255,.08) 50%,transparent 70%);background-size:200% 100%;animation:ep-demo-shimmer 2.4s linear infinite;"></div>
  <div style="font-size:40px;margin-bottom:8px;">🎬</div>
  <div style="font-weight:800;font-size:18px;letter-spacing:.06em;">DEMO AD</div>
  <div style="opacity:.75;font-size:13px;margin-top:6px;">Placeholder creative — paste real network code<br>in <strong>/admin/ads</strong> to go live.</div>
  <div style="display:inline-block;margin-top:12px;background:#f5c518;color:#1a1a2e;font-weight:800;font-size:13px;padding:6px 16px;border-radius:999px;">+5 🪙 on completion</div>
</div>
<style>@keyframes ep-demo-shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}</style>
HTML;
    }
}
