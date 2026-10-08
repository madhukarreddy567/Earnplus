<?php

namespace Database\Seeders;

use App\Models\OfferwallProvider;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Offerwall providers: the 8 real networks (all DISABLED until the admin
 * adds real API credentials) plus the "Demo" sandbox provider (enabled)
 * with 5 sample tasks for end-to-end testing.
 *
 * Real provider credentials are added later in the admin panel — nothing
 * here phones home.
 */
class OfferwallSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Wannads',
                'slug' => 'wannads',
                'config' => [
                    'tagline' => 'Offers, surveys and app installs',
                    'max_payout_coins' => 5000,
                    'offer_url_template' => null, // set after approval
                    'postback_format' => 'GET/POST {app_postback_url}?subid={click_uid}&transaction_id={tx}&payout={payout}&signature={hmac}',
                    'docs' => 'Wannads publisher panel → Postback URL: the EarnPlus /postback/wannads URL with their macros for subid/transaction/payout.',
                ],
            ],
            [
                'name' => 'BitLabs',
                'slug' => 'bitlabs',
                'config' => [
                    'tagline' => 'High-paying surveys',
                    'max_payout_coins' => 8000,
                    'offer_url_template' => null,
                    'postback_format' => 'GET {app_postback_url}?transaction_id={tx}&user_id={uid}&payout={payout}&signature={hmac}',
                    'docs' => 'BitLabs dashboard → Server postback: point at /postback/bitlabs and map their transaction/user/payout parameters.',
                ],
            ],
            [
                'name' => 'AdGate Media',
                'slug' => 'adgate',
                'config' => [
                    'tagline' => 'Offer wall with daily bonuses',
                    'max_payout_coins' => 6000,
                    // Verification: official AdGate docs checked 2026-10-06.
                    'verification_status' => 'verified',
                    'verified_sources' => [
                        'https://github.com/adgatemedia/adgaterewards (official wall + postback docs & PHP examples)',
                        'https://help.adgatemedia.com/hc/en-us/articles/360000995774-What-is-a-postback/',
                    ],
                    'offer_url_template' => null, // set after approval
                    // Verified wall format (official README): iframe the wall,
                    // USER_ID = any string up to 255 chars -> pass our user id.
                    'offer_url_template_example' => 'https://wall.adgaterewards.com/{WALL_ID}/{user_id}',
                    // Give AdGate this URL in panel -> Monetization Tools ->
                    // AdGate Rewards -> Create wall -> Postback field.
                    'postback_url' => 'https://YOUR-DOMAIN/postback/adgate', // <-- replace YOUR-DOMAIN
                    'postback_method' => 'GET',
                    // Verified param names from AdGate's official postback
                    // example (postback_pdo_example.php): tx_id, user_id,
                    // offer_id, status (status=0 means CHARGEBACK/reversal).
                    // The payout macro name is chosen in the panel's postback
                    // URL ("More Information on Postbacks" lists the macros) —
                    // confirm it matches 'points' below, or edit the mapping
                    // in the admin panel.
                    'param_map' => [
                        'tx_id' => 'provider_tx_id',
                        'user_id' => 'user_id',
                        'points' => 'payout',
                        'offer_id' => 'offer_id',
                        'status' => 'status',
                    ],
                    'postback_fields' => [
                        'tx_id' => 'provider_tx_id (their unique transaction id — dedupe key)',
                        'user_id' => 'user_id (our user id, passed in the wall iframe URL)',
                        'points' => 'payout (points awarded; confirm the macro name in the panel)',
                        'offer_id' => 'offer id (logged, informational)',
                        'status' => '1 = conversion, 0 = CHARGEBACK (logged for admin review, never auto-credited)',
                    ],
                    // AdGate's public postback spec documents NO signature
                    // parameter, so the engine skips HMAC for this provider.
                    // IP whitelist + click validation + dedupe still apply.
                    // If your AdGate panel offers a security token, change
                    // this to 'hmac_raw_body' and paste the token as the
                    // postback secret in the admin panel.
                    'signature' => 'none',
                    'chargeback_param' => 'status',
                    'chargeback_values' => ['0'],
                    'docs' => 'AdGate panel -> Monetization Tools -> AdGate Rewards -> Create wall: set currency names + conversion rate, paste the EarnPlus /postback/adgate URL (with your payout macro) in the Postback field, then place the wall iframe. Keep this provider DISABLED until approved.',
                ],
            ],
            [
                'name' => 'CPX Research',
                'slug' => 'cpx-research',
                'config' => [
                    'tagline' => 'Survey router',
                    'max_payout_coins' => 4000,
                    'offer_url_template' => null,
                    'postback_format' => 'GET {app_postback_url}?trans_id={tx}&user_id={uid}&amount={payout}&hash={hmac}',
                    'docs' => 'CPX publisher settings → Postback URL: /postback/cpx-research.',
                ],
            ],
            [
                'name' => 'OfferToro',
                'slug' => 'offertoro',
                'config' => [
                    'tagline' => 'Offers and video ads',
                    'max_payout_coins' => 5000,
                    'offer_url_template' => null,
                    'postback_format' => 'GET {app_postback_url}?oid={transaction_id}&uid={user_id}&amount={payout}&sig={hmac}',
                    'docs' => 'OfferToro dashboard → Postback: /postback/offertoro.',
                ],
            ],
            [
                'name' => 'TimeWall',
                'slug' => 'timewall',
                'config' => [
                    'tagline' => 'Micro-tasks and offers',
                    // With the placement at 5000 coins per $1 USD, a $1 task
                    // credits 5000 coins — cap comfortably above that.
                    'max_payout_coins' => 50000,
                    // Verification: VERIFIED 2026-10-07 from the owner's
                    // TimeWall site-owner dashboard (Placement -> Add
                    // Placement -> Postback Setup). Exact parameter names,
                    // the {hash} scheme and the server IPs below are verbatim
                    // from that dashboard.
                    // NOTE: TimeBucks is a GPT site (a competitor), NOT this
                    // provider — do not confuse them.
                    'verification_status' => 'verified',
                    'verified_sources' => [
                        'TimeWall site-owner dashboard (owner-verified 2026-10-07): Placement -> Postback Setup — exact param names, {hash} = SHA256(userID . revenue . SecretKey), server IPs',
                    ],
                    // The iframe wall URL is issued by the TimeWall dashboard
                    // after the placement is created (Placements -> Add
                    // Placement -> Offerwall (iFrame)).
                    'offer_url_template' => null, // paste the dashboard iframe URL after creating the placement
                    'offer_url_template_example' => 'https://timewall.io/... (iframe URL from your TimeWall dashboard -> Placements)',
                    'postback_url' => 'https://YOUR-DOMAIN/postback/timewall', // <-- replace YOUR-DOMAIN
                    // Exact postback URL to paste in TimeWall (Placement ->
                    // Postback Setup -> Postback URL), with their macros:
                    'postback_url_template' => 'https://YOUR-DOMAIN/postback/timewall?userid={userID}&txid={transactionID}&revenue={revenue}&currency={currencyAmount}&hash={hash}&ip={ip}&type={type}&withdrawid={withdrawid}&reason={reason}&offername={offername}&offerdetail={offerdetail}',
                    'postback_method' => 'GET',
                    // Verified parameter names from the TimeWall dashboard.
                    'param_map' => [
                        'userid' => 'user_id',
                        'txid' => 'provider_tx_id',
                        'revenue' => 'revenue_usd',
                        'currency' => 'coins',
                        'hash' => 'signature',
                        'ip' => 'tw_ip',
                        'type' => 'tw_type',
                        'withdrawid' => 'tw_withdrawid',
                        'reason' => 'tw_reason',
                        'offername' => 'tw_offername',
                        'offerdetail' => 'tw_offerdetail',
                    ],
                    'postback_fields' => [
                        'userid' => 'user_id (our user id, passed as {userID} in the wall iframe URL)',
                        'txid' => 'provider_tx_id (their unique transaction id — dedupe key)',
                        'revenue' => 'revenue_usd (USD revenue, RAW string — used verbatim in the hash check)',
                        'currency' => 'coins (currencyAmount: coins per your placement conversion rate — credited when sane)',
                        'hash' => 'signature: SHA256 hex of (userID . revenue . SecretKey), no separators',
                        'ip' => "user's IP (logged, informational)",
                        'type' => 'event type — non-earning types (withdrawals etc.) are logged, never credited',
                        'withdrawid' => 'withdrawal id, when type is withdrawal-related (logged)',
                        'reason' => 'reason text (logged, informational)',
                        'offername' => 'offer name (logged, informational)',
                        'offerdetail' => 'offer detail (logged, informational)',
                    ],
                    // Signature: the {hash} macro returns
                    // hash('sha256', userID . revenue . SecretKey) —
                    // concatenated with NO separator, using the revenue value
                    // EXACTLY as received in the query string (e.g. "0.002",
                    // never rounded/padded/reformatted). Paste the Secret Key
                    // (TimeWall dashboard -> Postback Setup -> REVEAL) as this
                    // provider's postback secret in the admin panel.
                    'signature' => 'timewall_sha256',
                    // TimeWall's postback servers (dashboard 2026-10-07):
                    // 18.156.132.55 is active; 51.81.120.73 and
                    // 142.111.248.18 are being retired — whitelist all three
                    // for now (seeded into the ip_whitelist column below).
                    'timewall_ips' => ['18.156.132.55', '51.81.120.73', '142.111.248.18'],
                    // Coins credited = the `currency` (currencyAmount) param
                    // when present and sane; otherwise revenue_usd x this
                    // rate. Match it to the placement's Currency Conversion
                    // Rate. Owner's decision 2026-10-07: Coins @ 5000 per $1
                    // USD (approx 55% user / 45% owner at 100 Coins = ₹1).
                    // Placement a3604f711b1e95d4 created 2026-10-07, status
                    // PENDING APPROVAL (TimeWall review) — iframe URL is
                    // issued after approval.
                    'timewall_currency_rate' => 5000,
                    // Event-type hints that must NEVER credit (conservative
                    // case-insensitive substring match). TimeWall does not
                    // document the full list of `type` values — ask their
                    // support to confirm if you see unexpected types.
                    'non_earning_type_hints' => ['withdraw', 'reversal', 'chargeback', 'refund', 'cancel'],
                    'docs' => 'TimeWall dashboard -> Placements -> Add Placement: Placement Type = Offerwall (iFrame), Placement Category = Reward Site, Redeem Type = Manual Redeem, Currency Name = Coins, Currency Conversion Rate = 5000, decimals = No. Placement a3604f711b1e95d4 created 2026-10-07 (PENDING APPROVAL). Paste the EarnPlus postback URL (postback_url_template above, with YOUR-DOMAIN replaced) into Postback Setup, REVEAL the Secret Key and paste it as this provider\'s postback secret here. Keep DISABLED until approved. NOTE: TimeWall only approves sites with 5000+ monthly visitors.',
                ],
            ],
            [
                'name' => 'AdGem',
                'slug' => 'adgem',
                'config' => [
                    'tagline' => 'Mobile offers and surveys',
                    'max_payout_coins' => 7000,
                    // Verification: official AdGem docs checked 2026-10-06.
                    'verification_status' => 'verified',
                    'verified_sources' => [
                        'https://docs.adgem.com/docs/integrate/offer-delivery/pre-built/web-offerwall (wall URL, player_id rules, server postback)',
                        'https://docs.adgem.com/.../quickstart (App Property -> Postback Options -> Server Postback)',
                    ],
                    'offer_url_template' => null, // set after approval
                    // Verified wall format (official docs): direct link or
                    // iframe. player_id: REQUIRED, lowercase, alphanumeric +
                    // hyphens/underscores only, max 255 chars -> our numeric
                    // user id is safe.
                    'offer_url_template_example' => 'https://api.adgem.com/v1/wall?appid={APP_ID}&playerid={user_id}',
                    // AdGem dashboard -> Properties & Apps -> your property ->
                    // Postback Options -> Server Postback -> paste this URL
                    // with the macros below.
                    'postback_url' => 'https://YOUR-DOMAIN/postback/adgem', // <-- replace YOUR-DOMAIN
                    'postback_method' => 'GET',
                    // Verified macro names (AdGem dashboard Postback Settings):
                    // build the postback URL alphabetically as AdGem requires
                    // for v2 hashing, e.g.
                    // /postback/adgem?amount={amount}&campaign_id={campaign_id}
                    //   &goal_id={goal_id}&goal_name={goal_name}
                    //   &offer_name={offer_name}&payout={payout}
                    //   &player_id={player_id}&transaction_id={transaction_id}
                    // AdGem appends request_id + verifier itself.
                    'param_map' => [
                        'player_id' => 'user_id',
                        'transaction_id' => 'provider_tx_id',
                        'payout' => 'payout',
                        'amount' => 'amount',
                        'campaign_id' => 'campaign_id',
                        'offer_name' => 'offer_name',
                    ],
                    'postback_fields' => [
                        'player_id' => 'user_id (our user id, passed as playerid in the wall URL)',
                        'transaction_id' => 'provider_tx_id (their unique transaction id — dedupe key)',
                        'payout' => 'payout (reward in YOUR currency units as configured in AdGem)',
                        'amount' => 'raw amount (logged, informational)',
                        'campaign_id' => 'campaign id (logged, informational)',
                        'offer_name' => 'offer name (logged, informational)',
                        'request_id' => 'appended by AdGem v2 (used in signature check)',
                        'verifier' => 'v2 HMAC signature appended by AdGem',
                    ],
                    // AdGem "v2 Server Postback hashing": enable it in the
                    // dashboard's Postback Settings and paste the one-time
                    // Postback Key as this provider's postback secret here.
                    // The engine checks verifier = HMAC-SHA256 over the
                    // alphabetically-sorted query string (excluding verifier
                    // and request_id). CONFIRM this exact computation against
                    // your AdGem publisher docs before going live.
                    'signature' => 'adgem_v2',
                    'docs' => 'AdGem dashboard -> Properties & Apps -> New Property (Desktop/Web), copy the App ID; Postback Options -> Server Postback -> paste the EarnPlus /postback/adgem URL with the macros above (alphabetical order); enable v2 Server Postback hashing and paste the Postback Key as the secret. Keep DISABLED until approved and the verifier is confirmed.',
                ],
            ],
            [
                'name' => 'Rewards Offerwall',
                'slug' => 'rewards-offerwall',
                'config' => [
                    'tagline' => 'Curated reward offers',
                    'max_payout_coins' => 4000,
                    'offer_url_template' => null,
                    'postback_format' => 'GET {app_postback_url}?tx={transaction_id}&user={user_id}&payout={payout}&sig={hmac}',
                    'docs' => 'RewardsOfferwall panel → Postback URL: /postback/rewards-offerwall.',
                ],
            ],
        ];

        foreach ($providers as $row) {
            OfferwallProvider::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'enabled' => false,
                    'postback_secret' => OfferwallProvider::generateSecret(),
                    'user_revenue_share' => 70.00,
                    'config' => $row['config'],
                    'sandbox_mode' => false,
                ]
            );
        }

        // Demo sandbox provider — enabled, serves 5 sample tasks in-app.
        OfferwallProvider::updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Tasks',
                'enabled' => true,
                'postback_secret' => OfferwallProvider::generateSecret(),
                'user_revenue_share' => 100.00,
                'sandbox_mode' => true,
                'config' => [
                    'tagline' => 'Try the full earn flow — no real provider needed',
                    'tasks' => [
                        [
                            'key' => 'app-install',
                            'title' => 'Install the Demo Quest app',
                            'kind' => 'App install',
                            'description' => 'Install and open the demo app for 30 seconds.',
                            'payout_coins' => 120,
                        ],
                        [
                            'key' => 'survey',
                            'title' => 'Complete a 5-minute survey',
                            'kind' => 'Survey',
                            'description' => 'Answer a few questions about your shopping habits.',
                            'payout_coins' => 200,
                        ],
                        [
                            'key' => 'signup',
                            'title' => 'Sign up for Demo Rewards',
                            'kind' => 'Signup bonus',
                            'description' => 'Create a free demo account with your e-mail.',
                            'payout_coins' => 80,
                        ],
                        [
                            'key' => 'video',
                            'title' => 'Watch a 30-second video',
                            'kind' => 'Video',
                            'description' => 'Watch the full demo video without skipping.',
                            'payout_coins' => 30,
                        ],
                        [
                            'key' => 'quiz',
                            'title' => 'Answer a 3-question quiz',
                            'kind' => 'Quiz',
                            'description' => 'Get at least 2 out of 3 answers right.',
                            'payout_coins' => 50,
                        ],
                    ],
                ],
            ]
        );

        // TimeWall's postback server IPs (verified from their dashboard):
        // seed the whitelist once — never overwrite an admin's own list.
        $timewall = OfferwallProvider::where('slug', 'timewall')->first();
        if ($timewall !== null && ($timewall->ip_whitelist === null || trim($timewall->ip_whitelist) === '')) {
            $timewall->update([
                'ip_whitelist' => implode("\n", $timewall->config['timewall_ips'] ?? []),
            ]);
        }

        // Offerwall-related settings (all in DB, nothing hardcoded).
        $settings = [
            ['key' => 'device_fingerprint_salt', 'value' => Str::random(32), 'group' => 'offerwalls'],
            ['key' => 'offerwall_click_expiry_hours', 'value' => '24', 'group' => 'offerwalls'],
            ['key' => 'offerwall_max_clicks_per_hour', 'value' => '20', 'group' => 'offerwalls'],
        ];

        foreach ($settings as $row) {
            if (Setting::where('key', $row['key'])->doesntExist()) {
                Setting::set($row['key'], $row['value'], $row['group']);
            }
        }
    }
}
