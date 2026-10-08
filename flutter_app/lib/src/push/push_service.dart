/// Push notification service — STUB, intentionally never initialized.
///
/// Why this exists as a stub:
///   • The `firebase_messaging` dependency is present in pubspec.yaml so the
///     integration point is ready.
///   • There is NO google-services.json in the project and the google-services
///     Gradle plugin is NOT applied — the build must succeed without Firebase.
///   • [PushService.initialize] is never called from anywhere.
///
/// To activate push (owner's job, documented in lib/README.md):
///   1. Add `android/app/google-services.json` from the Firebase console.
///   2. Apply the google-services Gradle plugin in android/app/build.gradle.
///   3. Call `await PushService.initialize()` from main() after Firebase
///      is configured, and uncomment the real implementation below.
///
/// Until then, calling initialize() is a documented no-op.
library;

import 'package:flutter/foundation.dart';

class PushService {
  PushService._();

  // `final` because nothing may initialize push without the owner's
  // Firebase setup (see file header); the commented implementation below
  // would flip this to a settable field when activated.
  static final bool _initialized = false;
  static bool get isInitialized => _initialized;

  /// NEVER called by the app today. Kept as the single activation point.
  static Future<void> initialize() async {
    // Intentionally a no-op: no google-services.json / Gradle plugin in the
    // project, so FirebaseApp cannot be created. See the file header and
    // lib/README.md for the owner's activation checklist.
    debugPrint(
        'PushService.initialize() called but Firebase is not configured — '
        'no-op. See lib/README.md "Push notifications".');
  }

  // ── Real implementation (uncomment after Firebase setup) ──────────────
  // static Future<void> initialize() async {
  //   await Firebase.initializeApp();
  //   final messaging = FirebaseMessaging.instance;
  //   await messaging.requestPermission();
  //   final token = await messaging.getToken();
  //   // POST the FCM token to the backend so it can target this device.
  //   _initialized = true;
  //   FirebaseMessaging.onMessage.listen(_onForegroundMessage);
  //   FirebaseMessaging.onBackgroundMessage(_onBackgroundMessage);
  // }
  // ─────────────────────────────────────────────────────────────────────
}
