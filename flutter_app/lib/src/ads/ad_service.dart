/// Rewarded-ads orchestration for AdMob + Unity Ads.
///
/// ═══════════════════════════════════════════════════════════════════════════
/// REWARD MODEL — SERVER-TO-SERVER (S2S) CREDITING. READ BEFORE TOUCHING.
/// ═══════════════════════════════════════════════════════════════════════════
/// The app NEVER credits coins for watching an ad. The flow is:
///
///   1. User watches a rewarded ad (AdMob or Unity).
///   2. The ad network calls the BACKEND's server-to-server verify endpoint
///      (/ads/verify/admob or /ads/verify/unity) with its signed callback.
///   3. The backend validates the network's signature and credits the user's
///      wallet.
///   4. The app passes the EarnPlus user id in `custom_data` (AdMob) /
///      `serverId` (Unity) so the backend knows whose wallet to credit.
///   5. When the ad closes, the app simply RE-READS the wallet from the
///      server ([ApiClient.getWallet]) and shows the fresh balance.
///
/// Client-side crediting would let anyone grant themselves coins with a
/// rooted device or a replayed callback — that is why it is forbidden here.
/// If you are tempted to add coins in `onAdRewarded`, don't: refresh from
/// the server instead.
///
/// What the owner must configure (see lib/README.md):
///   • AdMob: real App ID in AndroidManifest.xml (meta-data
///     com.google.android.gms.ads.APPLICATION_ID) + real rewarded ad unit ID
///     in backend admin → /api/config ads.admob.
///   • Unity: real Game IDs (Android/iOS) + rewarded placement ID in backend
///     admin → /api/config ads.unity. Unity `serverId` carries the user id
///     for S2S attribution.
/// ═══════════════════════════════════════════════════════════════════════════
library;

import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'package:unity_ads_plugin/unity_ads_plugin.dart';

import '../api/models.dart';

/// Google's official test ids (used whenever the backend has no real ids).
class _TestIds {
  static const admobRewardedAndroid = 'ca-app-pub-3940256099942544/5224354917';
  static const admobRewardedIos = 'ca-app-pub-3940256099942544/1712485313';
  static const unityGameId = 'test-game-id';
  static const unityPlacement = 'rewardedVideo';
}

class AdService {
  bool _admobReady = false;
  bool _unityReady = false;
  String? _unityPlacement;
  String? _unityInterstitialPlacement;

  bool get admobReady => _admobReady;
  bool get unityReady => _unityReady;

  /// The interstitial placement id from /api/config (empty when unconfigured).
  String get unityInterstitialPlacement => _unityInterstitialPlacement ?? '';

  /// Call once at startup (after /api/config loads).
  Future<void> initialize(AppConfig config) async {
    await _initAdMob(config);
    await _initUnity(config);
  }

  // ------------------------------------------------------------------ AdMob

  Future<void> _initAdMob(AppConfig config) async {
    try {
      // The App ID itself lives in AndroidManifest.xml; here we only need
      // the SDK initialized. When the backend has no ids we still init so
      // test ads work during development.
      await MobileAds.instance.initialize();
      _admobReady = true;
    } catch (e) {
      debugPrint('AdMob init failed: $e');
      _admobReady = false;
    }
  }

  /// Resolves the rewarded unit id: backend value wins, else Google's test id.
  @visibleForTesting
  static String resolveAdMobRewardedUnit(AdMobConfig? config) {
    final fromServer = config?.rewardedAdUnitId.trim() ?? '';
    if (fromServer.isNotEmpty) return fromServer;
    return Platform.isIOS
        ? _TestIds.admobRewardedIos
        : _TestIds.admobRewardedAndroid;
  }

  /// Shows a rewarded AdMob ad. [userId] is sent as `custom_data` so the
  /// backend's S2S verify endpoint can attribute the reward.
  /// [onAdClosed] fires when the ad dismisses — the caller must refresh the
  /// wallet from the server there (never credit locally).
  Future<bool> showAdMobRewarded({
    required AppConfig config,
    required String userId,
    required void Function() onAdClosed,
    void Function(String error)? onFailed,
  }) async {
    if (!_admobReady) {
      onFailed?.call('AdMob not initialized');
      return false;
    }
    final unitId = resolveAdMobRewardedUnit(config.ads.admob);
    var completed = false;
    try {
      await RewardedAd.load(
        adUnitId: unitId,
        request: const AdRequest(),
        rewardedAdLoadCallback: RewardedAdLoadCallback(
          onAdLoaded: (ad) async {
            ad.fullScreenContentCallback = FullScreenContentCallback(
              onAdDismissedFullScreenContent: (ad) {
                ad.dispose();
                // S2S: backend credits via /ads/verify/admob; we just report
                // the close so the caller can re-read the wallet.
                onAdClosed();
              },
              onAdFailedToShowFullScreenContent: (ad, error) {
                ad.dispose();
                onFailed?.call(error.message);
              },
            );
            // S2S attribution: the EarnPlus user id travels to the backend
            // inside the ad network's signed server-side verification
            // callback (custom_data), so the backend knows whose wallet
            // to credit. The app itself never credits coins.
            await ad.setServerSideOptions(
              ServerSideVerificationOptions(customData: userId),
            );
            ad.show(onUserEarnedReward: (ad, reward) {
              // Intentionally empty: the reward is credited by the backend's
              // server-to-server callback, NOT here. See the header comment.
            });
            completed = true;
          },
          onAdFailedToLoad: (error) => onFailed?.call(error.message),
        ),
      );
    } catch (e) {
      onFailed?.call(e.toString());
      return false;
    }
    return completed;
  }

  // ------------------------------------------------------------------ Unity

  Future<void> _initUnity(AppConfig config) async {
    final unity = config.ads.unity;
    final gameId = unity == null || unity.gameIdAndroid.isEmpty
        ? _TestIds.unityGameId
        : (Platform.isIOS && unity.gameIdIos.isNotEmpty
            ? unity.gameIdIos
            : unity.gameIdAndroid);
    final testMode = unity == null || unity.gameIdAndroid.isEmpty;
    _unityPlacement = unity?.rewardedPlacementId.isNotEmpty == true
        ? unity!.rewardedPlacementId
        : _TestIds.unityPlacement;
    _unityInterstitialPlacement = unity?.interstitialPlacementId ?? '';
    try {
      final done = Completer<bool>();
      await UnityAds.init(
        gameId: gameId,
        testMode: testMode,
        onComplete: () => done.complete(true),
        onFailed: (_, __) {
          if (!done.isCompleted) done.complete(false);
        },
      );
      _unityReady = await done.future.timeout(
        const Duration(seconds: 10),
        onTimeout: () => false,
      );
      if (_unityReady && _unityPlacement != null) {
        await UnityAds.load(placementId: _unityPlacement!);
      }
    } catch (e) {
      debugPrint('Unity Ads init failed: $e');
      _unityReady = false;
    }
  }

  /// Shows a rewarded Unity ad. [userId] is sent as `serverId` for S2S
  /// attribution — pass [serverIdOverride] to send a different value
  /// (the spin-claim gate sends "{userId}:spin:{claimToken}" so the
  /// backend can match Unity's S2S callback to the pending claim).
  /// Same rule as AdMob: no client-side crediting — the caller
  /// refreshes the wallet from the server when the ad completes/closes.
  Future<void> showUnityRewarded({
    required String userId,
    String? serverIdOverride,
    required void Function() onFinished,
    void Function(String error)? onFailed,
  }) async {
    if (!_unityReady || _unityPlacement == null) {
      onFailed?.call('Unity Ads not ready');
      return;
    }
    try {
      await UnityAds.showVideoAd(
        placementId: _unityPlacement!,
        serverId: serverIdOverride ?? userId, // S2S attribution.
        onComplete: (_) {
          // Backend credits via /ads/verify/unity. Caller re-reads wallet.
          onFinished();
        },
        onSkipped: (_) => onFinished(),
        onFailed: (_, __, message) => onFailed?.call(message),
      );
    } catch (e) {
      onFailed?.call(e.toString());
    }
  }

  /// Shows a Unity interstitial ad (non-rewarded). Returns true when an
  /// ad was actually shown, false on no-fill / load failure / not ready.
  /// Callers must treat false as "skip silently" — never surface errors
  /// to the user for interstitials.
  Future<bool> showUnityInterstitial() async {
    final placement = _unityInterstitialPlacement;
    if (!_unityReady || placement == null || placement.isEmpty) {
      return false;
    }
    try {
      await UnityAds.load(placementId: placement);
      var shown = false;
      await UnityAds.showVideoAd(
        placementId: placement,
        onComplete: (_) => shown = true,
        onSkipped: (_) => shown = true,
        onFailed: (_, __, ___) => shown = false,
      );
      // Preload the next one; failures are silent (no-fill is normal).
      try {
        await UnityAds.load(placementId: placement);
      } catch (_) {}
      return shown;
    } catch (_) {
      return false;
    }
  }
}
