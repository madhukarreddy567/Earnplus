/// Registry that maps vendor keys → [EngagementProvider] implementations.
///
/// The app never `new`s a provider directly; it asks the registry, so
/// swapping vendors is a one-line change here plus the SDK dependency.
library;

import 'engagement_provider.dart';
import 'stub_engagement_provider.dart';

class EngagementRegistry {
  final Map<String, EngagementProvider> _providers = {};

  EngagementRegistry({List<EngagementProvider>? providers}) {
    // The stub is always registered so unknown/unconfigured keys degrade
    // gracefully instead of crashing.
    register(StubEngagementProvider());
    for (final p in providers ?? const []) {
      register(p);
    }
  }

  void register(EngagementProvider provider) {
    _providers[provider.key] = provider;
  }

  /// Returns the provider for [key], or the stub when unknown.
  EngagementProvider resolve(String key) =>
      _providers[key] ?? _providers['stub']!;

  List<EngagementProvider> get all => List.unmodifiable(_providers.values);

  Future<void> initializeAll() async {
    for (final p in _providers.values) {
      try {
        await p.initialize();
      } catch (_) {
        // One broken vendor must not kill the rest.
      }
    }
  }
}
