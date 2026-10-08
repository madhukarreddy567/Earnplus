/// [StubEngagementProvider] — placeholder wired through the registry so the
/// UI and tests exercise the real provider-resolution path without a vendor
/// SDK.
///
/// It does nothing except report "not configured". The owner replaces this
/// with a real implementation (see engagement_provider.dart) — it must never
/// be used to fake rewards in production.
library;

import 'package:flutter/foundation.dart';

import 'engagement_provider.dart';

class StubEngagementProvider implements EngagementProvider {
  @override
  String get key => 'stub';

  @override
  String get displayName => 'Engagement (not configured)';

  @override
  Future<void> initialize() async {
    debugPrint('StubEngagementProvider: no vendor SDK configured.');
  }

  @override
  Future<bool> get isReady async => false;

  @override
  Future<void> showRewarded({
    required String userId,
    required void Function() onComplete,
    void Function(String message)? onError,
  }) async {
    onError?.call('No engagement SDK configured. Ask the owner to plug in a '
        'real provider (see lib/src/engagement/engagement_provider.dart).');
  }
}
