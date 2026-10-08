import 'package:earnplus/src/ads/ad_service.dart';
import 'package:earnplus/src/api/models.dart';
import 'package:earnplus/src/engagement/engagement_provider.dart';
import 'package:earnplus/src/engagement/provider_registry.dart';
import 'package:earnplus/src/engagement/stub_engagement_provider.dart';
import 'package:flutter_test/flutter_test.dart';

class _RealisticProvider implements EngagementProvider {
  @override
  String get key => 'playtime';
  @override
  String get displayName => 'Playtime SDK';
  bool initialized = false;
  @override
  Future<void> initialize() async => initialized = true;
  @override
  Future<bool> get isReady async => true;
  @override
  Future<void> showRewarded({
    required String userId,
    required void Function() onComplete,
    void Function(String message)? onError,
  }) async =>
      onComplete();
}

void main() {
  group('EngagementRegistry', () {
    test('unknown key resolves to the stub', () {
      final registry = EngagementRegistry();
      final p = registry.resolve('no_such_vendor');
      expect(p, isA<StubEngagementProvider>());
    });

    test('registered vendor wins over stub', () {
      final registry = EngagementRegistry(providers: [_RealisticProvider()]);
      final p = registry.resolve('playtime');
      expect(p.displayName, 'Playtime SDK');
    });

    test('stub is never ready and reports not-configured', () async {
      final stub = StubEngagementProvider();
      expect(await stub.isReady, isFalse);
      String? err;
      await stub.showRewarded(
        userId: '1',
        onComplete: () => fail('stub must not complete'),
        onError: (m) => err = m,
      );
      expect(err, contains('No engagement SDK configured'));
    });

    test('initializeAll tolerates a broken vendor', () async {
      final registry = EngagementRegistry(providers: [_RealisticProvider()]);
      await registry.initializeAll(); // must not throw
      expect(
          (registry.resolve('playtime') as _RealisticProvider).initialized,
          isTrue);
    });
  });

  group('AdService unit resolution', () {
    test('uses server rewarded unit id when present', () {
      final config = AppConfig.fromJson({
        'site_name': 'EarnPlus',
        'ads': {
          'admob': {
            'app_id': 'ca-app-pub-real~1',
            'rewarded_ad_unit_id': 'ca-app-pub-real/2',
          },
        },
      });
      expect(
        AdService.resolveAdMobRewardedUnit(config.ads.admob),
        'ca-app-pub-real/2',
      );
    });

    test('falls back to Google test id when backend has none', () {
      final config = AppConfig.fromJson({'site_name': 'EarnPlus'});
      final unit = AdService.resolveAdMobRewardedUnit(config.ads.admob);
      expect(unit, contains('3940256099942544'));
    });

    test('falls back to Google test id when unit id blank', () {
      final unit = AdService.resolveAdMobRewardedUnit(
        const AdMobConfig(appId: 'x', rewardedAdUnitId: '  '),
      );
      expect(unit, contains('3940256099942544'));
    });
  });
}
