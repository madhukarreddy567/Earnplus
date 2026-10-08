/// Playtime-style engagement provider interface.
///
/// This is the seam where the owner plugs in a REAL engagement SDK
/// (playtime / rewarded-engagement network of their choice):
///
///   1. Implement [EngagementProvider] with the vendor's SDK calls.
///   2. Register it in [EngagementRegistry] under a key (e.g. 'playtime').
///   3. The app resolves providers by key from the backend's task-provider
///      list — no app update needed to switch vendors.
///
/// The same S2S rule as ads applies: the provider implementation must NOT
/// credit coins itself. It reports completion; the backend credits the
/// wallet server-to-server and the app re-reads the wallet. [onComplete]
/// means "the engagement finished", never "coins were granted".
library;

/// Contract every engagement SDK adapter must implement.
abstract class EngagementProvider {
  /// Vendor key, e.g. 'playtime', 'adgem_wall'.
  String get key;

  /// Human-readable name shown in the UI.
  String get displayName;

  /// Initialize the vendor SDK. Called once at startup.
  Future<void> initialize();

  /// True when the SDK is ready to present an engagement.
  Future<bool> get isReady;

  /// Present the rewarded engagement experience for [userId].
  ///
  /// Calls [onComplete] when the user finishes (or legitimately earns the
  /// reward per the vendor's callback). The implementation must NOT add
  /// coins — it only signals completion; the backend credits S2S.
  /// Calls [onError] with a human-readable message on failure.
  Future<void> showRewarded({
    required String userId,
    required void Function() onComplete,
    void Function(String message)? onError,
  });
}
