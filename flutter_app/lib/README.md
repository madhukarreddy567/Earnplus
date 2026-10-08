# EarnPlus Flutter App

The EarnPlus mobile app (Android-first; iOS structure kept valid). mPaisa-style
mobile-money UI in deep green (`#0B7A3E`). Talks to the EarnPlus Laravel backend.

## 1. Setup

### Point the app at the backend

There is exactly **one** place to configure the backend URL:

```
lib/config.dart  →  const String apiBaseUrl = 'https://earnplus.example.com';
```

For local development against Laravel on the host machine (Android emulator):

```dart
const String apiBaseUrl = 'http://10.0.2.2:8000';
```

Everything else (feature flags, branding, ad ids, withdraw presets) is loaded
at runtime from `GET /api/config` — no rebuild needed to change them.

### Build & test commands

```bash
# one-time env (see task brief for why these are needed in this VM)
export JAVA_TOOL_OPTIONS="-Djava.net.preferIPv4Stack=true"
export PATH="$HOME/workspace/flutter_sdk/flutter/bin:$PATH"
export JAVA_HOME=$HOME/workspace/.sdk/jdk-17.0.9+9
export ANDROID_HOME=$HOME/workspace/android-sdk
export GRADLE_USER_HOME=$HOME/workspace/.gradle

flutter pub get
flutter test            # 51 tests, must all pass
flutter analyze         # lints
flutter build apk --debug   # → build/app/outputs/flutter-apk/app-debug.apk
```

Do **not** attempt release signing from here — the owner signs with their own
keystore when publishing to Play.

### Build-environment notes (this VM, 2026-10-07)

The debug APK was built here with these VM-local workarounds — none of them
change app behavior, and all are documented so a rebuild elsewhere is
unaffected:

- **Maven proxy:** Gradle cannot reach the network directly from Java here
  (TLS-intercepting egress proxy). `~/workspace/maven-proxy.py` (port 8081,
  curl-based fetch + local cache) must be running — the Gradle init script in
  `~/workspace/.gradle/init.d/` already points at it. The proxy's CA
  (`/usr/local/share/ca-certificates/hatch-egress-ca.crt`) was imported into
  the workspace JDK's `cacerts` so proxied HTTPS works from Java; proxy
  credentials are passed transiently via `GRADLE_OPTS`, never written to disk.
- **Gradle dist:** the wrapper points at `gradle-8.14.3-all` (pre-cached in
  `~/workspace/.gradle/wrapper/dists/`).
- **Kotlin 2.4.0:** `android/settings.gradle.kts` pins
  `org.jetbrains.kotlin.android` 2.4.0 (same as the jarvis project in this
  workspace, whose dependency graph is fully cached here). The app's
  `build.gradle.kts` uses the `kotlin.compilerOptions` DSL (`jvmTarget`),
  required by Kotlin ≥ 2.0.
- **Pub-cache patches (VM-local only):** two Flutter plugins pin dependency
  versions whose artifacts are not in the local caches, so their
  `android/build.gradle` files in `~/.pub-cache` were bumped to cached
  versions: `share_plus-10.1.4` → `ext.kotlin_version = '2.4.0'`
  (was 1.7.22), `unity_ads_plugin-0.3.30` → AGP `8.9.1` (was 7.3.0).
  A fresh `flutter pub get` on another machine re-extracts the originals —
  harmless there, since those machines have real network.
- **Packaging:** `android/app/build.gradle.kts` excludes duplicate
  `META-INF/**/LICENSE.txt` and `META-INF/*.version` files (androidx.annotation
  and kotlinx-coroutines ship them in both JVM and Android variants).

### Regenerating icons / splash

```bash
dart run flutter_launcher_icons
dart run flutter_native_splash:create
```

Source art: `assets/icon/app_icon.png` (green rounded square + gold ₹ coin,
generated with Python PIL).

## 2. What needs the owner's accounts / keys

Nothing in the app hardcodes secrets. All of the below are entered in the
**backend admin panel** (which serves them via `/api/config`) or in platform
consoles — the app picks them up at runtime:

| What | Where the owner gets it | Where it goes |
|---|---|---|
| AdMob **App ID** | AdMob console → App settings | `android/app/src/main/AndroidManifest.xml` — replace the `com.google.android.gms.ads.APPLICATION_ID` meta-data (currently Google's official **test** id `ca-app-pub-3940256099942544~3347511713`). Required before any production release; without it real ads won't serve. |
| AdMob **rewarded ad unit ID** | AdMob console → Ad units | Backend admin → ad network settings → served as `ads.admob.rewarded_ad_unit_id` in `/api/config`. Falls back to Google's test unit `ca-app-pub-3940256099942544/5224354917` when absent. |
| Unity **Game IDs** (Android + iOS) | Unity Ads dashboard → Project | Backend admin → served as `ads.unity.game_id_android` / `game_id_ios`. App runs Unity in `testMode:true` until real ids arrive. |
| Unity **rewarded placement ID** | Unity Ads dashboard → Placements | Backend admin → served as `ads.unity.rewarded_placement_id`. |
| Google OAuth **Android client ID** | Google Cloud console → Credentials (Android OAuth client for `com.earnplus.app`) | Backend admin → served as `google_client_id_android`; also add the SHA-1 of the signing key in the Cloud console. |
| Google OAuth **iOS client ID** | Google Cloud console → Credentials (iOS OAuth client) | Backend admin → served as `google_client_id_ios`. |
| Firebase `google-services.json` | Firebase console → Project settings | `android/app/google-services.json` + apply the google-services Gradle plugin (see §5). **Not present today — push stays off until then.** |
| Backend base URL | the owner's server | `lib/config.dart` `apiBaseUrl` (the only code change needed per environment). |

Email login is hidden unless the backend sets `email_auth_enabled: true`
(default off). The Google button is hidden unless `google_auth_enabled: true`.

## 3. Reward model — server-to-server (S2S) crediting

**The app never credits coins for ads.** The flow, for both AdMob and Unity:

1. User watches a rewarded ad.
2. The ad network calls the **backend's** server-to-server verify endpoint
   (`/ads/verify/admob`, `/ads/verify/unity`) with its signed callback.
3. The backend validates the network signature and credits the wallet.
4. The app attaches the EarnPlus user id for attribution —
   AdMob via `ServerSideVerificationOptions(customData: userId)`,
   Unity via `serverId: userId` in `showVideoAd`.
5. When the ad closes/completes, the app **re-reads the wallet from the
   server** (`GET /api/wallet`) and displays the fresh balance.

`onUserEarnedReward` is intentionally left empty (see the header comment in
`lib/src/ads/ad_service.dart`). Client-side crediting would let a rooted
device or replayed callback mint coins — that is why it is forbidden.

The WebView fallback placement (`POST /api/ads/reward/{placement}`) is the
only ad reward credited through a direct API call, and it is still the
**backend** doing the crediting.

## 4. Engagement providers (playtime-style)

`lib/src/engagement/` defines the seam for rewarded-engagement SDKs:

- `engagement_provider.dart` — the `EngagementProvider` interface
  (`initialize` / `isReady` / `showRewarded`).
- `provider_registry.dart` — key → implementation map; unknown keys resolve
  to the stub so the UI degrades gracefully.
- `stub_engagement_provider.dart` — wired in today; reports "not configured".

To plug a real vendor: implement the interface with the vendor's SDK, register
it in `EngagementRegistry`, add the SDK dependency. Same S2S rule — the
provider reports completion; the backend credits.

## 5. Push notifications (currently OFF)

`firebase_messaging` is a dependency and `lib/src/push/push_service.dart`
exists as the single activation point, but:

- there is **no** `google-services.json` in the project,
- the google-services **Gradle plugin is not applied**,
- `PushService.initialize()` is **never called** (deliberately),

so `flutter build apk --debug` succeeds with zero Firebase setup. To activate,
the owner: adds `android/app/google-services.json`, applies the plugin in
`android/app/build.gradle.kts`, then calls `PushService.initialize()` from
`main()` and uncomments the real implementation inside that file.

## 6. Architecture

```
lib/
  config.dart                  # apiBaseUrl — the ONE backend URL setting
  main.dart                    # providers, startup order, ad init after config
  src/
    api/
      api_client.dart          # every endpoint, typed; ApiException(401/404/409/422/429)
      models.dart              # AppConfig, User, Wallet, transactions, spin,
                               # check-in, tasks, promotions, withdraw, referral
    auth/auth_service.dart     # token in flutter_secure_storage, user state
    config/app_config.dart     # runtime /api/config holder (ChangeNotifier)
    ads/ad_service.dart        # AdMob + Unity, S2S credit model (see §3)
    engagement/                # provider interface + registry + stub
    push/push_service.dart     # stub, never initialized (see §5)
    screens/                   # splash, login, home(+bottom nav), tasks,
                               # task_webview, spin, checkin, wallet, withdraw,
                               # referral, profile
    widgets/widgets.dart       # BalanceHero, QuickActionTile, PromoBanner, …
    theme.dart                 # EarnPlusColors + Material3 theme
```

State: `provider` — `AppConfigService` + `AuthService` as ChangeNotifiers,
`ApiClient`/`AdService`/`EngagementRegistry` as plain providers.

Money math: the backend works in **paise**; presets (default ₹10/₹20/₹30) come
from the server. Withdraw submits carry a client-generated **UUID v4
idempotency key** so retries/double-taps can't create duplicates.

Spin: the wheel renders the server's segment list and animates to the
server-returned `segment_index` — the outcome is never decided client-side.

## 7. Backend contract (summary)

Public: `GET /api/config`, `POST /api/auth/google {id_token, ref?}`,
`POST /api/auth/login {email, password}`.
Authenticated (`Authorization: Bearer <token>`): `POST /api/auth/logout`,
`GET /api/auth/me`, `GET /api/wallet`, `GET /api/transactions?page=N`,
`GET|POST /api/checkin[/status]`, `GET|POST /api/spin[/status]`,
`GET /api/tasks/providers`, `POST /api/tasks/click/{slug}`,
`GET /api/promotions`, `GET /api/withdraw/methods`,
`POST /api/withdraw/quote`, `POST /api/withdraw`,
`GET /api/withdrawals`, `GET /api/referral`,
`POST /api/ads/reward/{placement}`.
Full shapes: `lib/src/api/models.dart`.
