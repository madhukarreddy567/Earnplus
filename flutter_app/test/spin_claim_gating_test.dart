import 'package:earnplus/src/ads/ad_service.dart';
import 'package:earnplus/src/ads/interstitial_scheduler.dart';
import 'package:earnplus/src/api/api_client.dart';
import 'package:earnplus/src/auth/auth_service.dart';
import 'package:earnplus/src/screens/spin_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import 'test_helpers.dart';

/// Fake AdService: never touches platform channels.
class FakeAdService extends AdService {
  String? lastServerId;
  bool failNext = false;

  @override
  Future<void> showUnityRewarded({
    required String userId,
    String? serverIdOverride,
    required void Function() onFinished,
    void Function(String error)? onFailed,
  }) async {
    lastServerId = serverIdOverride ?? userId;
    if (failNext) {
      failNext = false;
      onFailed?.call('no fill');
      return;
    }
    onFinished();
  }
}

Map<String, dynamic> _statusJson() => {
      'enabled': true,
      'segments': [10, 20, 30, 40, 50, 60, 70, 80],
      'spins_left': 1,
      'daily_limit': 1,
      'ad_gate_enabled': true,
    };

Map<String, dynamic> _spinWinJson() => {
      'won': true,
      'amount': 50,
      'segment_index': 3,
      'segments': [10, 20, 30, 40, 50, 60, 70, 80],
      'spins_left': 0,
      'balance': 0,
      'claim_token': 'tok123',
      'claim_expires_at':
          DateTime.now().add(const Duration(minutes: 15)).toIso8601String(),
    };

Map<String, dynamic> _meJson() => {
      'user': {
        'id': 7,
        'name': 'Tester',
        'email': 't@example.com',
        'coins': 0,
        'rupees': 0.0,
      },
    };

class _Harness {
  final ApiClient api;
  final AuthService auth;
  final FakeAdService ads;
  final InterstitialScheduler scheduler;

  _Harness({
    required this.api,
    required this.auth,
    required this.ads,
    required this.scheduler,
  });

  Future<void> pump(WidgetTester t) async {
    await t.pumpWidget(
      MultiProvider(
        providers: [
          Provider<ApiClient>.value(value: api),
          ChangeNotifierProvider<AuthService>.value(value: auth),
          Provider<AdService>.value(value: ads),
          Provider<InterstitialScheduler>.value(value: scheduler),
        ],
        child: const MaterialApp(
          home: SpinScreen(
            claimPollEvery: Duration(milliseconds: 10),
            claimMaxPolls: 3,
          ),
        ),
      ),
    );
    await t.pumpAndSettle();
  }
}

Future<_Harness> _makeHarness(
  Map<String, Future<http.Response> Function(http.BaseRequest)> routes,
) async {
  final storage = FakeSecureStorage();
  await storage.write(key: 'earnplus_auth_token', value: 'tok');
  final api = ApiClient(
    httpClient: FakeHttp.routes({
      'GET /api/auth/me': (_) async => jsonRes(_meJson()),
      ...routes,
    }),
  );
  final auth = AuthService(api: api, storage: storage);
  await auth.restoreSession();
  return _Harness(
    api: api,
    auth: auth,
    ads: FakeAdService(),
    scheduler: InterstitialScheduler(
      interval: const Duration(minutes: 10),
      showAd: () async => false,
    ),
  );
}

void main() {
  group('SpinScreen claim gate', () {
    testWidgets('win shows Watch-Ad claim card (no instant credit)',
        (t) async {
      final h = await _makeHarness({
        'GET /api/spin/status': (_) async => jsonRes(_statusJson()),
        'POST /api/spin': (_) async => jsonRes(_spinWinJson()),
      });
      await h.pump(t);
      await t.tap(find.byKey(const Key('spin_button')));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('claim_card')), findsOneWidget);
      expect(find.byKey(const Key('watch_ad_button')), findsOneWidget);
      expect(find.textContaining('50 coins'), findsWidgets);
      // The old instant-credit snackbar must NOT appear.
      expect(find.textContaining('claimed!'), findsNothing);
    });

    testWidgets('happy path: ad -> verified -> claimed', (t) async {
      var claimed = false;
      final h = await _makeHarness({
        'GET /api/spin/status': (_) async => jsonRes(_statusJson()),
        'POST /api/spin': (_) async => jsonRes(_spinWinJson()),
        'GET /api/spin/claim/tok123': (_) async => jsonRes({
              'status': 'verified',
              'ad_verified': true,
              'expired': false,
              'claimed': false,
              'amount': 50,
            }),
        'POST /api/spin/claim': (_) async {
          claimed = true;
          return jsonRes({'coins': 50, 'balance': 50});
        },
        'GET /api/wallet': (_) async =>
            jsonRes({'coins': 50, 'rupees': 0.5, 'lifetime_earned': 50}),
      });
      await h.pump(t);
      await t.tap(find.byKey(const Key('spin_button')));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('watch_ad_button')));
      await t.pumpAndSettle();
      expect(claimed, isTrue);
      // serverId carries the claim token for S2S matching.
      expect(h.ads.lastServerId, '7:spin:tok123');
      expect(find.textContaining('claimed!'), findsWidgets);
      expect(h.auth.user?.coins, 50);
    });

    testWidgets('ad load failure shows retry, keeps claim, no credit',
        (t) async {
      var claimed = false;
      final h = await _makeHarness({
        'GET /api/spin/status': (_) async => jsonRes(_statusJson()),
        'POST /api/spin': (_) async => jsonRes(_spinWinJson()),
        'POST /api/spin/claim': (_) async {
          claimed = true;
          return jsonRes({'coins': 50, 'balance': 50});
        },
      });
      h.ads.failNext = true;
      await h.pump(t);
      await t.tap(find.byKey(const Key('spin_button')));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('watch_ad_button')));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('claim_error')), findsOneWidget);
      expect(find.byKey(const Key('watch_ad_button')), findsOneWidget,
          reason: 'retry button still offered');
      expect(claimed, isFalse, reason: 'never credit on ad failure');
    });

    testWidgets('ad skipped (never verified) shows not-completed',
        (t) async {
      var claimed = false;
      final h = await _makeHarness({
        'GET /api/spin/status': (_) async => jsonRes(_statusJson()),
        'POST /api/spin': (_) async => jsonRes(_spinWinJson()),
        // Unity never calls back: stays unverified through all polls.
        'GET /api/spin/claim/tok123': (_) async => jsonRes({
              'status': 'pending',
              'ad_verified': false,
              'expired': false,
              'claimed': false,
              'amount': 50,
            }),
        'POST /api/spin/claim': (_) async {
          claimed = true;
          return jsonRes({'coins': 50, 'balance': 50});
        },
      });
      await h.pump(t);
      await t.tap(find.byKey(const Key('spin_button')));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('watch_ad_button')));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('claim_error')), findsOneWidget);
      expect(find.textContaining('not completed'), findsOneWidget);
      expect(claimed, isFalse);
      // Retry is still available — the claim stays valid until expiry.
      expect(find.byKey(const Key('watch_ad_button')), findsOneWidget);
    });

    testWidgets('expired claim shows expiry message', (t) async {
      final h = await _makeHarness({
        'GET /api/spin/status': (_) async => jsonRes(_statusJson()),
        'POST /api/spin': (_) async => jsonRes({
              ..._spinWinJson(),
              'claim_expires_at': DateTime.now()
                  .subtract(const Duration(minutes: 1))
                  .toIso8601String(),
            }),
      });
      await h.pump(t);
      await t.tap(find.byKey(const Key('spin_button')));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('watch_ad_button')));
      await t.pumpAndSettle();
      expect(find.textContaining('expired'), findsOneWidget);
    });

    testWidgets('loss shows no-luck, no claim card', (t) async {
      final h = await _makeHarness({
        'GET /api/spin/status': (_) async => jsonRes(_statusJson()),
        'POST /api/spin': (_) async => jsonRes({
              'won': false,
              'amount': 0,
              'segment_index': 0,
              'segments': [10, 20, 30, 40, 50, 60, 70, 80],
              'spins_left': 0,
              'balance': 0,
              'claim_token': null,
              'claim_expires_at': null,
            }),
      });
      await h.pump(t);
      await t.tap(find.byKey(const Key('spin_button')));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('claim_card')), findsNothing);
      expect(find.textContaining('No luck'), findsOneWidget);
    });
  });
}
