# EarnPlus — all 13 phases complete ✅

GPT (get-paid-to) rewards app. **Phase 1** is the backend foundation:
Laravel 11 + MySQL 8, DB-driven settings, animation system, landing page, health check.
**Phase 2** adds full authentication: user signup/login with e-mail
verification, mobile OTP, reCAPTCHA, password reset — plus a completely
separate admin guard (`/admin/*`) with roles, login logging and lockout.
**Phase 3** adds the coin economy: ledger-first wallets, signup/referral/
daily-check-in/spin bonuses, the user wallet dashboard and admin wallet tools.
**Phases 4–5** add offerwalls & tasks and ads, followed by an unnumbered
mPaisa-style UI redesign with Google login. **Phases 6–8** add withdrawals,
the promotion engine, and branding/media design settings. **Phase 9**
completes the admin panel (statistics, settings, policy pages).
**Phase 10** hardens security (rate limits, IP blocking, security headers).
**Phase 11** adds the cron system. **Phase 12** ships the Flutter mobile
app. **Phase 13** adds the production/license/domain lock.

## Build status

| # | Phase | Status |
|---|---|---|
| 1 | Foundation (Laravel 11 + MySQL 8, settings, animations) | ✅ Done |
| 2 | Authentication (users, OTP, admin guard, lockout) | ✅ Done |
| 3 | Coins & wallet (ledger, bonuses, spin, referrals) | ✅ Done |
| 4 | Offerwalls & tasks (signed postbacks, providers) | ✅ Done |
| 5 | Ads (networks, placements, rewarded, AdMob/Unity S2S) | ✅ Done |
| — | Redesign — mPaisa-style UI, Google login, e-mail auth toggle | ✅ Done |
| 6 | Withdrawals (UPI/Paytm/bank/PayPal, payout drivers) | ✅ Done |
| 7 | Promotion engine (multipliers, banners, countdowns) | ✅ Done |
| 8 | Branding, media & design settings (uploads, themes) | ✅ Done |
| 9 | Admin-panel completion (statistics, settings, policy pages) | ✅ Done |
| 10 | Security (rate limiting, IP blocking, CSP, Cloudflare, lockdown) | ✅ Done |
| 11 | Cron system (scheduler, payout queue, dashboards) | ✅ Done |
| 12 | Flutter mobile app (native, mPaisa-style, SDK integrations) | ✅ Done |
| 13 | Production/license/domain lock (modes, signature, kill switch) | ✅ Done |

QA bar held for every phase: **20 consecutive full-suite runs, all green,
zero flakes.** Final suite: 557 tests, 1,979 assertions.

Planned stack: **Flutter** mobile app + **Laravel/MySQL** backend & admin panel
(Flutter arrives in a later phase).

## Requirements

- PHP 8.3+
- MySQL 8
- Composer 2
- Node 18+ / npm (only to vendor frontend assets — already vendored in `public/vendor/`)

## Setup

```bash
# 1. Create the database
mysql -u root -e "CREATE DATABASE earnplus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE USER 'earnplus'@'localhost' IDENTIFIED BY 'EarnPlus!2026'; \
  GRANT ALL PRIVILEGES ON earnplus.* TO 'earnplus'@'localhost'; FLUSH PRIVILEGES;"

# 2. Install PHP dependencies
composer install

# 3. Configure
cp .env.example .env          # then set DB_DATABASE=earnplus, DB_USERNAME=earnplus, DB_PASSWORD=...
php artisan key:generate

# 4. Migrate + seed
php artisan migrate --force
php artisan db:seed --force   # settings + default Super Admin (see below)
```

### First Super Admin login

The seeder creates one Super Admin from environment variables:

```bash
ADMIN_EMAIL=admin@earnplus.local          # default if not set
ADMIN_SEED_PASSWORD=ChangeMe123!         # default if not set — change immediately
php artisan db:seed --class=AdminSeeder --force
```

Log in at `/admin/login`. **Change the default password right after the
first login** — the default is only a placeholder, never a real password.

### Gmail SMTP for verification mails

The mailer is configured from DB settings at boot (`App\Services\MailSettings`):

| Setting key        | Seeded default   | Meaning                              |
|---|---|---|
| `mail_host`        | `smtp.gmail.com` | SMTP host                            |
| `mail_port`        | `587`            | SMTP port                            |
| `mail_username`    | *(empty)*        | Gmail address                        |
| `mail_password`    | *(empty)*        | **Gmail app password** (not your login password) |
| `mail_encryption`  | `tls`            | `tls` or `ssl`                       |
| `mail_from_address`| *(empty)*        | Falls back to `mail_username`        |
| `mail_from_name`   | `EarnPlus`       | Sender name                          |

While `mail_username` is empty, mails are **logged** (`storage/logs/laravel.log`)
instead of sent — verification flows keep working in dev and tests never break.

To go live: create a Google **app password**
(Google Account → Security → 2-Step Verification → App passwords),
then store it in the `settings` table (`mail_username` + `mail_password`)
via the admin panel (Phase 9) or:

```php
Setting::set('mail_username', 'you@gmail.com', 'mail');
Setting::set('mail_password', 'xxxx xxxx xxxx xxxx', 'mail');
```

### Mobile OTP

`otp_enabled` setting (default off). When on **and** the user registered with a
mobile number, a 6-digit code is required before the dashboard opens.
Codes are bcrypt-hashed, single-use, expire in 10 minutes. The default
delivery driver only **logs** the code (`LogOtpDriver`) — swap the
`OtpDriver` container binding for a real SMS gateway later; no caller changes.

### reCAPTCHA v2

`recaptcha_enabled` setting (default off) + `recaptcha_site_key` /
`recaptcha_secret_key` in settings. When on, register and login require a
valid token (verified against Google). When off, forms work without it.

### E-mail auth toggle

`email_auth_enabled` setting (default **OFF** — Google is the only sign-in).
When off, `/register`, `POST /login`, `/forgot-password` and
`/reset-password/*` all return 404 and every e-mail form/link vanishes
from the UI (the login page becomes Google-only). Flip it on in the admin
panel to restore classic e-mail signup/signin. The `/admin/login` guard
is unaffected — staff always use e-mail + password.

### "Continue with Google" (Socialite)

Google OAuth is fully inert until credentials are configured: the buttons
hide and `/auth/google*` 404s. To enable it, the **owner** creates the
credentials in the Google Cloud Console (5 minutes, one time):

1. Go to <https://console.cloud.google.com/> and create (or pick) a project.
2. **APIs & Services → OAuth consent screen** → choose *External* →
   fill the app name (`EarnPlus`), your support e-mail, and save.
3. **APIs & Services → Credentials → Create Credentials → OAuth client ID**
   → application type *Web application*.
4. Under **Authorized redirect URIs** add exactly:
   `https://YOUR-DOMAIN/auth/google/callback`
   (for local testing: `http://127.0.0.1:8000/auth/google/callback`).
5. Copy the **Client ID** and **Client secret**.
6. In EarnPlus, store them in the settings table (admin panel → site
   settings, Phase 9 — or via tinker):
   `google_client_id` and `google_client_secret` (group `auth`).
7. The "Continue with Google" buttons appear immediately — no deploy.

How it works: new Google users get an account auto-created with the
e-mail pre-verified, a referral code, and the standard signup bonus
(idempotent — never double-credits). An existing e-mail account with the
same address is linked instead of duplicated. A `?ref=` referral code on
the Google button survives the OAuth round-trip via the session.

### Mobile-SDK ad networks (AdMob, Unity Ads)

These networks render inside the **Flutter app** (native SDKs) — the
backend stores their IDs and verifies their server-to-server callbacks:

1. `/admin/ads` → enable the **AdMob** (or **Unity Ads**) network.
2. Fill the config keys (see `AdNetwork::configSchema()`):
   - AdMob: `app_id`, `rewarded_ad_unit_id`, `ssv_key_id` (optional).
   - Unity: `game_id_android`, `game_id_ios`, `rewarded_placement_id`, `s2s_secret`.
3. In the network's dashboard, point the reward callback at:
   `https://YOUR-DOMAIN/ads/verify/admob`
   (or `/ads/verify/unity-ads`).
   - AdMob: SSV postback URL — include `user_id` (our user id) in the
     SDK's `custom_data`, so it echoes back. The RSA signature is verified
     against Google's published keys (cached 24h).
   - Unity: S2S callback — pass our user id as `custom_data` (echoed as
     `oid`); the HMAC is verified with your `s2s_secret`.
4. Verified callbacks credit coins idempotently
   (`s2s:{network}:{transaction_id}`) under the same daily limit as web
   rewarded ads. Retries can never double-pay.
5. The Flutter app (later phase) loads the IDs from a backend endpoint
   and shows the native rewarded ad.

### Adding any new offerwall / ad network (the 5-step pattern)

The engine is provider-neutral — a new network is configuration, not code:

1. **Seed or create** the provider/network row (offerwalls: `OfferwallSeeder`;
   ads: `AdSeeder`) — slug, name, disabled by default, generated secret.
2. **Postback contract**: point their server URL at
   `https://YOUR-DOMAIN/postback/{slug}` (offerwalls, HMAC-SHA256 of the
   raw body with `postback_secret`, `X-Signature` header or `?sig=`) or
   `https://YOUR-DOMAIN/ads/verify/{slug}` (mobile ads, network-native
   signature). Map their macros to our fields
   (`provider_tx_id`, `user_id`, `payout`) — document the mapping in the
   row's `config` (see the AdGate/TimeWall entries as examples).
3. **Offer URL**: set `offer_url_template` with `{click_uid}` / `{user_id}`
   placeholders for click tracking.
4. **Revenue share**: set `user_revenue_share` (offerwalls) or per-placement
   `coins` (ads).
5. **Enable** in the admin panel after the network approves your publisher
   account. The demo/sandbox rows stay for regression testing.

### Admin security

- Separate `admin` guard — `/admin/login` is a different page and session
  from the user login; a user session can never open `/admin/*` and an
  admin session can never open the user dashboard.
- Roles: `super_admin` (can open `/admin/admins`) vs `admin` (403 there).
- Every login attempt (both guards) is logged to `login_logs`
  (e-mail, IP, user agent, success, guard).
- 8 failed admin attempts lock the account for 15 minutes.

## Run

```bash
php artisan serve --port=8000
# Landing page : http://127.0.0.1:8000/
# Health check : http://127.0.0.1:8000/health
```

## What's inside

| Path | Purpose |
|---|---|
| `app/Models/Setting.php` | Key/value settings model (cached reads, `get/set/boolean/integer`) |
| `app/helpers.php` | `setting($key, $default)`, `setting_bool()`, `setting_int()` globals |
| `database/migrations/*_create_settings_table.php` | `settings` table (key unique, value, group) |
| `database/seeders/SettingSeeder.php` | Defaults: `site_name=EarnPlus`, `coins_per_rupee=100`, feature toggles |
| `app/Http/Controllers/HealthController.php` | `GET /health` → `{app, status, database, time}` |
| `resources/views/layouts/app.blade.php` | Base layout, Bootstrap 5 + anime.js loaded **locally** from `public/vendor/` |
| `resources/views/landing.blade.php` | Animation showcase: staggered hero, coin count-ups, hover-lift cards, skeleton demo |
| `public/css/app.css` | Custom styles: skeleton shimmer, cards, coin badge (transform/opacity only) |
| `public/js/animations.js` | `EarnPlus` module: page entrances, count-ups, float, press feedback, skeleton resolve |
| `public/vendor/` | Bootstrap 5.3.3 + anime.js 3.2.2 vendored locally — **no CDN** |

### Phase 2 — authentication

| Path | Purpose |
|---|---|
| `app/Models/Admin.php` | Admin user (`role`: super_admin/admin, `is_active`) |
| `app/Models/LoginLog.php` | Login attempt log (e-mail, IP, agent, success, guard) |
| `app/Models/OtpCode.php` | Hashed, single-use, expiring OTP codes |
| `app/Services/OtpService.php` + `app/Services/Otp/` | OTP generate/verify; pluggable `OtpDriver` (log driver default) |
| `app/Services/RecaptchaService.php` | reCAPTCHA v2 verification, active only when enabled + keyed |
| `app/Services/AdminLockout.php` | 8 fails → 15-minute admin lockout (per e-mail) |
| `app/Services/MailSettings.php` | Mailer from DB settings; empty credentials → log driver |
| `app/Http/Middleware/Authenticate.php` | Guard-aware login redirects (`/admin/*` → admin login) |
| `app/Http/Middleware/EnsureMobileVerified.php` | OTP gate before the dashboard (`mobile.verified`) |
| `app/Http/Middleware/EnsureAdminRole.php` | Role gate, e.g. `admin.role:super_admin` |
| `app/Http/Controllers/Auth/` | Register, Login (5/min throttle), e-mail verify, password reset, OTP |
| `app/Http/Controllers/Admin/` | Admin login/logout, dashboard, admin list (super admin only) |
| `database/seeders/AdminSeeder.php` | Super Admin from `ADMIN_EMAIL` / `ADMIN_SEED_PASSWORD` env |
| `resources/views/auth/` | Login, register, forgot/reset, verify e-mail, verify OTP pages |
| `resources/views/admin/` | Separate admin login, dashboard, admin accounts list |

### Route map (auth)

```
/register /login /logout /dashboard          (e-mail auth — 404 unless enabled)
/auth/google /auth/google/callback          (Google sign-in — 404 until configured)
/email/verify  /email/verify/{id}/{hash}  /email/verification-notification
/forgot-password  /reset-password/{token}  /reset-password   (404 unless e-mail auth enabled)
/otp/verify  /otp/resend
/checkin  (POST — daily check-in)
/spin  (GET — wheel page)   /spin  (POST — play, JSON)
/admin/login  /admin/logout  /admin  /admin/admins   (admin guard)
/admin/wallets  /admin/wallets/{user}  /admin/wallets/{user}/adjust  (admin guard)
/admin/offerwalls  /admin/offerwalls/create  /admin/offerwalls/{provider}/edit  (admin guard)
/admin/offerwalls/conversions  (admin guard)
/admin/ads  /admin/ads/networks/*  /admin/ads/placements/*  (admin guard)
/postback/{provider:slug}  (POST — signed, no session auth)
/ads/verify/{network:slug}  (GET/POST — AdMob SSV / Unity S2S, signature-verified)
/tasks  /tasks/out/{provider:slug}  /tasks/demo/{task}/complete  (user auth)
/ads/reward/{placement:slug}  (POST — web rewarded claim, user auth)
```

### Phase 3 — coins & wallet

| Path | Purpose |
|---|---|
| `app/Services/CoinService.php` | **The only way coins move**: `credit()`/`debit()` inside DB transactions with row locks; append-only `coin_transactions` ledger with `balance_after`; unique idempotency keys make double-credit impossible |
| `app/Services/CheckinService.php` | Daily check-in: once per calendar day, streak tracking, streak bonus every N days (all from settings) |
| `app/Services/SpinService.php` | Spin wheel: server-side outcome (`random_int`), admin settings for range/limit/win probability, IP + device fingerprint logged per spin |
| `app/Services/ReferralService.php` | Referral code resolution + referrer reward (self-referral blocked) |
| `app/Models/Wallet.php` | Per-user balance cache (`coins`, `lifetime_earned`) |
| `app/Models/CoinTransaction.php` | Ledger rows: type, amount, source, reference, meta, balance_after, idempotency_key |
| `app/Models/DailyCheckin.php` | One row per user per day + streak |
| `app/Models/SpinHistory.php` | Every spin attempt with IP/fingerprint |
| `app/Http/Controllers/CheckinController.php` | `POST /checkin` |
| `app/Http/Controllers/SpinController.php` | Wheel page + JSON spin endpoint (outcome never trusts the client) |
| `app/Http/Controllers/Admin/WalletController.php` | Wallet list + search, user ledger with filters, manual adjust (super admin, reason mandatory) |
| `resources/views/dashboard.blade.php` | Wallet hero (animated count-up, ₹ equivalent), check-in/spin cards, referral card, history |
| `resources/views/spin.blade.php` | SVG prize wheel, anime.js rotation landing on the server-chosen segment |
| `resources/views/admin/wallets/` | Admin wallet list + user ledger views |
| `app/helpers.php` | `coins_to_rupees()` (admin-editable `coins_per_rupee`), `format_rupees()` |

New settings (all in DB): `daily_checkin_streak_bonus`, `daily_checkin_streak_days`,
`spin_min_coins`, `spin_max_coins`, `spin_daily_limit`, `spin_win_probability`,
`banner_enabled`, `banner_title`, `banner_subtitle`.

Tests (Phase 3): 61 tests covering credit/debit math, idempotency, signup bonus
(incl. no double-credit), referrals (incl. self-referral block), check-in
(once-per-day, streaks, streak bonus), spin (server-side outcome, limits,
disabled state, client tampering), ₹ conversion, admin adjust + ledger filters,
dashboard rendering.

### Phase 4 — offerwalls & tasks

| Path | Purpose |
|---|---|
| `app/Models/OfferwallProvider.php` | Provider: slug, enabled, HMAC secret, IP whitelist, revenue share, config, sandbox mode |
| `app/Models/OfferwallClick.php` | Click rows: unique click_uid, IP, device fingerprint, status, expiry |
| `app/Models/OfferwallConversion.php` | Conversion rows: unique (provider, provider_tx_id), payout/user coins, status, raw payload |
| `app/Services/OfferwallService.php` | Click tracking, device fingerprints, 20-clicks/hour velocity cap, shared-device flagging |
| `app/Services/PostbackService.php` | Signature + IP verification, dedupe, click validation, credit via CoinService (idempotent), manual admin credit/reject |
| `app/Http/Controllers/PostbackController.php` | `POST /postback/{provider:slug}` — signed, CSRF-exempt, no session auth |
| `app/Http/Controllers/OfferwallClickController.php` | `GET /tasks/out/{provider:slug}` — records click, redirects with `{click_uid}` substituted |
| `app/Http/Controllers/TaskController.php` | `/tasks` page + demo sandbox: sample-task "complete" buttons self-post through the REAL signed endpoint |
| `app/Http/Controllers/Admin/OfferwallController.php` | Provider CRUD, secret regeneration, conversion log + filters, manual credit/reject |
| `database/seeders/OfferwallSeeder.php` | 8 real providers (all **disabled**) + enabled "Demo" sandbox with 5 sample tasks |
| `resources/views/tasks/index.blade.php` | Tasks page: provider cards + demo tasks, staggered anime.js entrances |
| `resources/views/admin/offerwalls/` | Provider list/stats, create-edit form, conversion log |

**How a postback works**

1. User clicks out via `/tasks/out/{slug}` → click row created (IP + device
   fingerprint, 24h expiry), user redirected to the provider's wall with
   `{click_uid}` in the URL.
2. Provider calls `POST /postback/{slug}` with JSON
   `{provider_tx_id, user_id, payout, click_uid?}` and an `X-Signature`
   header = `HMAC-SHA256(raw_body, postback_secret)` (`?sig=` also accepted).
3. EarnPlus verifies the signature, the optional IP whitelist, then requires a
   live click (else the conversion is logged as `rejected`).
4. Duplicate `provider_tx_id` values return `200 {status: duplicate}` and
   credit nothing. New conversions credit
   `user_coins = payout × user_revenue_share%` via `CoinService` with the
   idempotency key `offerwall:{slug}:{provider_tx_id}`.
5. Same device fingerprint across >3 users is flagged (`shared_device`) in the
   conversion meta; >20 clicks/hour per user is blocked.

**Connecting a real provider** (all 8 are seeded disabled — enable after approval)

For each provider, open its publisher dashboard, set the postback/server-URL to
`https://YOUR-DOMAIN/postback/{slug}`, map their macros to our fields, and
paste the `postback_secret` shown in the admin edit page:

| Provider | Postback URL | Map their fields → ours |
|---|---|---|
| Wannads | `/postback/wannads` | subid→`click_uid`, transaction_id→`provider_tx_id`, payout→`payout`, user id→`user_id` |
| BitLabs | `/postback/bitlabs` | transaction_id→`provider_tx_id`, user_id→`user_id`, payout→`payout` |
| AdGate Media | `/postback/adgate` | tx→`provider_tx_id`, user→`user_id`, points→`payout` |
| CPX Research | `/postback/cpx-research` | trans_id→`provider_tx_id`, user_id→`user_id`, amount→`payout` |
| OfferToro | `/postback/offertoro` | oid→`provider_tx_id`, uid→`user_id`, amount→`payout` |
| TimeWall | `/postback/timewall` | tx→`provider_tx_id`, user→`user_id`, payout→`payout` |
| AdGem | `/postback/adgem` | transaction_id→`provider_tx_id`, player_id→`user_id`, payout→`payout` |
| Rewards Offerwall | `/postback/rewards-offerwall` | tx→`provider_tx_id`, user→`user_id`, payout→`payout` |

The signature is always `HMAC-SHA256` of the **raw request body** with the
provider's `postback_secret`. Optionally restrict `ip_whitelist` to the
provider's postback IPs (one IP or CIDR per line, empty = any).

New settings (all in DB): `device_fingerprint_salt`, `offerwall_click_expiry_hours` (24),
`offerwall_max_clicks_per_hour` (20).

Tests (Phase 4): 49 tests — signature valid/invalid/missing, `?sig=` fallback,
IP whitelist allow/deny/CIDR, duplicate postbacks credit once, unknown/disabled
provider, click tracking + redirect + placeholder substitution, conversion
without/expired/consumed click rejected, revenue-share math, velocity cap,
shared-device flagging, admin CRUD + secret regeneration + idempotent manual
credit/reject, demo sandbox end-to-end through the real signed endpoint.

### Phase 5 — ads

| Path | Purpose |
|---|---|
| `app/Models/AdNetwork.php` | Ad network: slug, enabled, type (adsense/adsterra/monetag/propellerads/custom) |
| `app/Models/AdPlacement.php` | Placement: network, slot, type, device, pages, frequency cap, priority, coins, custom_code |
| `app/Models/AdImpression.php` | Every served impression (placement, user, IP, device, page) |
| `app/Models/AdReward.php` | Every rewarded payout (unique idempotency key) |
| `app/Services/AdService.php` | Slot rendering: device/page eligibility, session frequency caps, weighted priority rotation, impression logging |
| `app/Services/RewardedAdService.php` | Rewarded claims: daily limit, server-side minimum interval, hourly velocity, shared-device check, credit via CoinService |
| `app/Http/Controllers/RewardedAdController.php` | `POST /ads/reward/{placement:slug}` → JSON `{coins, balance}` |
| `app/Http/Controllers/Admin/AdController.php` | `/admin/ads`: network CRUD, placement CRUD, impression stats, reward log |
| `app/helpers.php` | `render_ad('slot')` helper |
| `resources/views/components/ad.blade.php` | `<x-ad placement="slot" />` — renders nothing when ineligible |
| `resources/views/ads/reward-card.blade.php` | "Watch ad & earn" card with anime.js countdown |
| `public/js/ads.js` | Rewarded card logic: countdown → fetch claim → coin burst |
| `database/seeders/AdSeeder.php` | 4 real networks (all **disabled**) + enabled "Demo" network with a 5-coin rewarded placement |

**How an ad slot works**

1. A view calls `<x-ad placement="dashboard-banner" />`.
2. `AdService` finds placements for that slot that are enabled **and** on an
   enabled network, match the device (mobile/desktop/all) and the current page,
   and are under their per-session frequency cap.
3. One placement is picked by **weighted rotation** (higher priority wins more
   often), an impression is logged, and its `custom_code` is output as-is.
4. No eligible placement → empty string; the layout never breaks.

**How a rewarded ad works**

1. The tasks page shows a "Watch ad & earn" card when a rewarded placement is
   eligible (the seeded demo pays 5 coins).
2. The user watches the creative through a countdown timer (anime.js,
   cosmetic only) and clicks Claim.
3. `POST /ads/reward/{slug}` re-verifies **everything server-side**: rewarded
   type, enabled state, daily limit (`rewarded_ad_daily_limit`, default 10),
   minimum interval since the last reward (`rewarded_ad_min_interval_seconds`,
   default 20 — the client timer is never trusted), hourly velocity
   (`rewarded_ad_max_per_hour`, default 5) and shared-device abuse
   (>3 users on one fingerprint per day).
4. Coins are credited through `CoinService` (source `rewarded_ad`) with the
   idempotency key `rewarded:{user}:{placement}:{date}:{n}` — a retried or
   double-fired request can never double-credit.

**Pasting real ad code** (after each network approves your publisher account)

1. `/admin/ads` → enable the network (or add a new one).
2. Add/edit a placement for the slot you want (`dashboard-banner`,
   `tasks-native`, `sidebar`, `interstitial`, `rewarded`), choose the device
   and pages, and **paste the network's code** into the "Ad code" field:
   - **AdSense:** Ads → By ad → pick a Display ad unit → copy the code.
   - **Adsterra:** publisher panel → the banner/popunder/native code for your site.
   - **Monetag:** Sites → copy the tag (popunder, vignette banner, etc.).
   - **PropellerAds:** Sites → copy the zone code.
3. Save. The slot renders the code on the next page view — no deploy needed.

**Trust model:** `custom_code` is rendered exactly as pasted (admin JavaScript
runs by design — that is how ad networks work). Only admin accounts can edit
it, so treat the field with the same care as admin access itself.

**Slots:** `dashboard-banner` (dashboard page), `tasks-native` (tasks page),
`sidebar` (sticky right rail on desktop, xl screens and up — the rail stays
hidden until a placement is eligible), `interstitial` (full-screen overlay
between page views, session-capped via the placement's frequency cap; never
shown inside the admin panel), `rewarded` (the "Watch ad & earn" card).

**Navbar:** auth-aware — guests see the signup-bonus badge + "Get Started",
logged-in users see their live coin balance + an account menu
(Dashboard / Tasks / Spin / Log out), admins see an admin menu instead.

New settings (all in DB): `rewarded_ad_daily_limit` (10),
`rewarded_ad_countdown_seconds` (15), `rewarded_ad_min_interval_seconds` (20),
`rewarded_ad_max_per_hour` (5).

Tests (Phase 5): 39 tests — placement eligibility (device/page/disabled/
network-disabled), session frequency cap, weighted rotation (deterministic
boundaries + distribution), impression logging, custom code rendered as-is,
empty slot renders nothing, rewarded happy path, client-timer bypass blocked
by server minimum interval, daily limit, hourly velocity, shared-device block,
session cap on claims, 403 for disabled/non-rewarded, 404 for unknown,
idempotency (unique keys + duplicate-key race), admin network/placement CRUD
+ stats + reward log, demo rewarded end-to-end wallet credit.

### Redesign pass — mPaisa-style UI, Google login, e-mail auth toggle

| Path | Purpose |
|---|---|
| `app/Http/Controllers/Auth/GoogleAuthController.php` | "Continue with Google" (Socialite): new-user creation (verified + bonus + referral), existing-user linking |
| `app/Http/Middleware/EnsureEmailAuthEnabled.php` | `email.auth` — e-mail auth routes 404 when the toggle is off |
| `app/Services/Ads/S2sVerifier.php` | Contract for mobile-network reward verifiers |
| `app/Services/Ads/AdmobSsvVerifier.php` | AdMob SSV: RSA signature verified against Google's published keys (cached 24h) |
| `app/Services/Ads/UnityS2sVerifier.php` | Unity S2S: HMAC-SHA256 of sorted params with the dashboard secret |
| `app/Services/Ads/AdVerificationService.php` | `/ads/verify/{network}`: verify → daily-limit → idempotent credit |
| `app/Http/Controllers/AdVerificationController.php` | S2S callback endpoint (GET/POST, signed, CSRF-exempt) |
| `app/Models/AdNetwork.php` | New types `admob` / `unity_ads` + `configSchema()` + `isMobileSdk()` |
| `resources/views/layouts/app.blade.php` | Mobile app shell: top app bar, bottom nav (Home/Tasks/Check-in FAB/Spin/Invite), auth-aware |
| `resources/views/layouts/auth.blade.php` | New auth card design |
| `resources/views/components/google-button.blade.php` | "Continue with Google" button (hides when unconfigured) |
| `resources/views/dashboard.blade.php` | Balance hero, quick-action grid, check-in card, activity list, referral card |
| `resources/views/landing.blade.php` | Mobile-money hero with phone mockup, features, how-it-works |
| `resources/views/tasks/index.blade.php` | Provider/task cards in the new style |
| `resources/views/spin.blade.php` | Wheel in the new card stage (wheel logic untouched) |
| `resources/views/ads/reward-card.blade.php` | Rewarded card in the new style |
| `resources/views/admin/` | Admin login + dashboard restyled; all admin views inherit the new tokens |
| `public/css/app.css` | v2 design system: tokens, shell, topbar, bottom nav, hero, quick actions, tx lists, Google button |

New settings (all in DB): `email_auth_enabled` (0),
`google_client_id` (''), `google_client_secret` ('').

Tests (redesign): Google OAuth (mocked Socialite: create/link/returning/
referral/disabled), e-mail toggle OFF (routes 404, UI clean, admin
unaffected) / ON (restores), AdMob/Unity types + schemas + S2S verify
(401/credit/idempotent retry), AdGate/TimeWall provider specs, redesign
page renders (hero, bottom nav, balance pill, Google button).

### Phase 6 — withdrawals

| Path | Purpose |
|---|---|
| `app/Models/Withdrawal.php` | Request row: integer money only (paise/cents), statuses pending→processing/completed (+approved/rejected/failed) |
| `app/Models/WithdrawalMethod.php` | Payout methods (upi/paytm/bank/cashfree/razorpay/payu/paypal_manual), limits, config, driver resolution |
| `app/Services/Withdrawals/WithdrawalService.php` | Integer money math, request flow, admin approve/reject (idempotent, refunds exact) |
| `app/Services/Withdrawals/PayoutDriver.php` | Driver contract: `payout()` → completed/processing/failed/manual |
| `app/Services/Withdrawals/ManualPayoutDriver.php` | Default: admin pays by hand |
| `app/Services/Withdrawals/CashfreePayoutDriver.php` | Cashfree Payouts API (live only with keys, else manual fallback) |
| `app/Services/Withdrawals/RazorpayPayoutDriver.php` | RazorpayX Payouts API (live only with keys, else manual fallback) |
| `app/Services/Withdrawals/PayUPayoutDriver.php` | PayU Payouts API (live only with keys, else manual fallback) |
| `app/Http/Controllers/WithdrawController.php` | `/withdraw` (methods, presets ₹10/20/30, live quote, details validation) |
| `app/Http/Controllers/Admin/WithdrawalController.php` | `/admin/withdrawals` (queue, log, approve/reject, method CRUD) |
| `resources/views/withdraw/` | mPaisa-style request page + status detail |
| `resources/views/admin/withdrawals/` + `admin/withdrawal_methods/` | Admin queue/log/detail + method editor |

Money is **integers only** — paise for INR, cents for USD. Coins→paise via
`coins_per_rupee` (100 coins = ₹1). Optional tax (`withdrawal_tax_enabled` /
`withdrawal_tax_percent`, off by default). PayPal pays USD via `usd_per_rupee`.
Coins debit immediately on request; a rejection refunds the exact coins
(`withdrawal_refund`, idempotent). Max 3 requests/user/day
(`withdrawal_max_per_day`). API drivers activate only when their keys are
pasted into the method config in the admin panel — empty keys = manual mode.

New settings (all in DB): `withdrawal_max_per_day` (3),
`withdrawal_tax_enabled` (0), `withdrawal_tax_percent` (0).

Tests (withdrawals): integer math (coins→paise, tax on/off, USD), presets +
custom validation, min/max, insufficient balance, idempotent double-submit,
daily limit, UPI/IFSC/PayPal-email validation, disabled-method blocking,
admin approve→driver→status flow, reject→exact refund→idempotent re-reject,
manual fallback, Cashfree/Razorpay/PayU with faked HTTP, log filters,
dashboard tile.

### Animation system

```html
<div data-animate data-delay="120">…</div>   <!-- fade + rise entrance -->
<div data-animate="pop" data-delay="200">…</div> <!-- scale pop entrance -->
<span data-countup="100">0</span>            <!-- animated count-up -->
<div data-float>…</div>                      <!-- gentle infinite float -->
<div class="skeleton skeleton-line"></div>   <!-- shimmer placeholder -->
```

Everything animates transform/opacity only (60fps). Honors `prefers-reduced-motion`.

### Phase 7 — promotion engine

| Path | Purpose |
|---|---|
| `app/Models/Promotion.php` | Promotion: name/slug, enabled, multiplier (1.1–10), scope, optional provider, start/end, banner + badge copy, priority |
| `app/Services/PromotionService.php` | `activePromotions()` / `applyMultipliers()` / `bannerFor()` / `badgeFor()`; best_only vs multiply stacking with cap |
| `app/Http/Controllers/Admin/PromotionController.php` | `/admin/promotions`: CRUD, enable/disable toggle, overlap warnings |
| `resources/views/admin/promotions/` | Index grouped Active / Upcoming / Expired / Disabled; form with datetime-local inputs |
| `resources/views/components/promo-banner.blade.php` | Festival banner with live countdown (`data-countdown-to`) |
| `database/seeders/PromotionSeeder.php` | "Diwali Dhamaka (demo)": 2x global, disabled, past dates — expired demo data, never pays |

**How a promotion works**

1. Admin creates a promotion at `/admin/promotions` with a date window,
   a scope (`global` or one earning path), an optional single provider
   (task scope only), and a multiplier.
2. Every earning path (tasks, spin, check-in, rewarded ads, referrals)
   asks `PromotionService::applyMultipliers()` for the final coin amount.
   No live promotion → the base amount passes through untouched.
3. Stacking (`promotion_stacking` setting, default `best_only`):
   `best_only` pays the highest live multiplier; `multiply` stacks them
   multiplicatively, capped by `promotion_max_stacked_multiplier`
   (default 5.0). Coins are always rounded **down** — fractions never pay.
4. The credited transaction records `promotion_ids` + `base_amount` in its
   meta, so every boosted payout is auditable.
5. Users see a festival banner with a live countdown on the dashboard and
   tasks page, plus gold `2X`-style badges on the affected quick-action
   tiles and task cards.

### Phase 8 — branding, media & design settings

| Path | Purpose |
|---|---|
| `app/Services/BrandingService.php` | Upload processing (intervention/image, GD): logo ≤512px wide, favicon 32px + 180px apple-touch, banner + 1200px + 768px variants, background ≤1920px, email header ≤600px, avatar 256px square crop. Old files deleted on replace; paths in `branding_*` DB settings |
| `app/Http/Controllers/Admin/BrandingController.php` | `/admin/branding`: media tab (6 upload cards + live preview) and design tab |
| `resources/views/admin/branding/index.blade.php` | Tabbed admin page; remove buttons use hidden DELETE forms with confirm |
| `resources/views/vendor/mail/html/header.blade.php` | Mail header override: shows the uploaded email header (≤600px) in verification / password e-mails, else the site name |
| `database/seeders/BrandingSeeder.php` | Design defaults via firstOrCreate — never overwrites admin choices |
| `branding_url()` helper | Absolute URL with `?v=` mtime cache-busting, or null (views render defaults) |

**Media uploads** — `POST /admin/branding/media` (`type` + `file`): images only
(jpg/png/webp), max 5MB. Files live under `storage/app/public/branding/`
(public disk; `php artisan storage:link`). Removing an asset clears its
settings and the site falls back to built-in defaults.

**Design settings** — `POST /admin/branding/design`: primary / accent /
hero-gradient colors (validated `#rrggbb`), font scale (90/100/110%),
button shape (rounded/pill/square), light/dark theme, background style
(cover/blur), and the master `animations_enabled` switch. The layout emits
all of them as CSS variables in `<head>` — changes apply instantly, no
rebuild. Dark theme swaps the design tokens via `html[data-theme="dark"]`.
The master animation switch adds the `no-anime` class (reduced-motion is
always respected regardless).

Helpers: `design_color($key, $default)` (never emits broken CSS),
`shade_color($hex, $percent)` (derives `--ep-primary-dark` / `-deep`).

### Phase 9 — admin-panel completion

| Path | Purpose |
|---|---|
| `app/Services/StatisticsService.php` | Users, coins, revenue, impressions aggregates for the dashboard |
| `app/Http/Controllers/Admin/SettingsController.php` | `/admin/settings`: tabbed site settings (general, features, maintenance) |
| `app/Http/Controllers/Admin/PolicyPageController.php` | Rich-text policy pages (terms/privacy/refund/about) with version history |
| `app/Http/Middleware/EnsureNotInMaintenance.php` | Maintenance mode: visitors see the 503 page, admins pass through |
| `resources/views/maintenance.blade.php` | Standalone polished maintenance page (custom message from settings) |

New settings: `maintenance_mode`, `maintenance_message`, plus feature/module
toggles (`offerwalls_enabled`, `ads_enabled`, `promotions_enabled`, …) enforced
by the `module.enabled` middleware.

### Phase 10 — security

| Path | Purpose |
|---|---|
| `app/Http/Middleware/EnsureNotBlocked.php` | IP blocklist enforcement (single IP or CIDR, optional expiry) |
| `app/Http/Middleware/SecurityHeaders.php` | CSP (nonce-based, zero-CDN), X-Frame-Options, X-Content-Type-Options, Referrer-Policy, optional HSTS |
| `app/Http/Middleware/TrustConfiguredProxies.php` | Cloudflare-compatible trusted proxies from DB settings |
| `app/Services/IpBlockService.php` | Blocklist matching incl. the "never block your own IP" safety |
| `app/Http/Controllers/Admin/SecurityController.php` | `/admin/security`: login logs, security events, blocklist, proxy settings, emergency lockdown |

Rate limiters (`login`, `admin-login`, `admin-write`, `postback`, `otp`) are
configured in `AppServiceProvider`. Uploads are re-encoded (payload
stripping), renamed randomly, and MIME + dimension checked. Custom
no-debug 404/500 pages; login errors never reveal which field was wrong.

### Phase 11 — cron system

| Path | Purpose |
|---|---|
| `routes/console.php` | Scheduler: daily maintenance, promotion cleanup, payout queue, log cleanup, session cleanup — all `withoutOverlapping()` |
| `app/Services/CronJobRegistry.php` | Single source of truth for jobs; heartbeat; dashboard alerts |
| `app/Services/PayoutQueueService.php` | Retries pending payouts transactionally with backoff; stuck rows flagged for manual review |
| `app/Models/CronRun.php` | Run history (ok/warning/failed) |
| `app/Http/Controllers/Admin/CronController.php` | `/admin/crons`: job health, heartbeat, per-job "Run now", 50-row history |

If the server has no system cron, the dashboard shows the dead heartbeat and
documents the `php artisan schedule:run` snippet; the admin "Run now" button
is the manual fallback.

### Phase 12 — Flutter mobile app

Native Flutter app (mPaisa-style, mirroring the web UI) talking to the
Laravel backend over the Sanctum JSON API (`routes/api.php`). Google Sign-In
shares the web `GoogleUserResolver`; AdMob and Unity Ads render natively
and credit coins only through the server-to-server verify endpoints
(`POST /postback/{slug}`, `/ads/verify/{slug}`) — never client-side.

### Phase 13 — production/license/domain lock

| Path | Purpose |
|---|---|
| `app/Services/LicenseService.php` | Modes, signature check (`HANDRIKAMADHUKARREDDY`), domain matching, kill switch, remote-kill signing |
| `app/Http/Middleware/EnsureLicensed.php` | Enforces the lock on web + API (JSON for the Flutter app); admin area, `/license/*` and health checks always pass |
| `app/Http/Controllers/Admin/LicenseController.php` | `/admin/license`: status, mode/domain settings, violation log, kill switch, remote-kill secret |
| `app/Models/LicenseViolation.php` | `license_violations` table: domain_mismatch / signature_tamper / kill_switch / domain_unconfigured |
| `database/seeders/LicenseSeeder.php` | Defaults: development mode, build signature, unlocked (never overwrites admin choices) |
| `resources/views/license-locked.blade.php` | Neutral 503 lock page (no internals leaked) |
| `resources/views/license-unlicensed.blade.php` | Neutral 403 "not licensed for this domain" page |

**Modes** — `app_mode` setting (`development`/`production`), `APP_MODE` env
as fallback, development is the safe default. Development is lenient: the
demo offerwall provider stays visible and a small "development mode" ribbon
shows in the UI. Production is strict: the stored `license_signature` must
equal the build signature, and the request host must match
`licensed_domain` (www variant + configured aliases accepted) — mismatches
are logged as violations and served a neutral page; after
`license_violation_block_threshold` violations from one IP in 24h (default
10, 0 disables), requests are hard-blocked. The signature is also verified
on boot in production. The demo sandbox is hidden in production on both
the web tasks page and the mobile API (`/api/tasks/providers` + click).

**Going live** — on the admin license page: set the licensed domain (the
form refuses a domain that doesn't match the host you're on, so you can't
lock yourself out), flip mode to **production**. Point your real domain at
the server; everything else keeps working.

**Kill switch** — super-admin only, double-confirmed on the license page:
locks every user route instantly (503 lock page; the Flutter app gets a JSON
`{"locked": true}`). Admin login keeps working so you can unlock.
**Remote kill** (default off): enable it on the license page to get a signed
URL (`/license/remote-kill?token=…`) you can trigger from anywhere; the
secret is shown only there and is regeneratable.

### Settings rule

**Every toggle lives in the `settings` DB table — nothing is hardcoded.**
Read with `setting('otp_enabled')`, `setting_bool('spin_enabled')`,
`setting_int('coins_per_rupee', 100)`. Writes via `Setting::set()` bust the cache.

## Tests

```bash
php artisan test
```

Feature tests cover: settings CRUD, the `setting()` helper (incl. defaults),
typed helpers, seeded defaults, the landing page, `/health`, plus Phase 2 —
registration validation, e-mail verification, login/logout + throttling,
password reset, OTP toggle on/off, reCAPTCHA toggle on/off, admin guard
separation, super admin seeder, login IP logging, account lockout, and
DB-driven mailer config.
Test DB is `earnplus_test` (see `phpunit.xml`) — the dev database is never touched.

Phase 1+2+3+4+5 QA bar: **20 consecutive full-suite runs, all green, zero flakes.**
