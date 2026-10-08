import 'dart:convert';

import 'package:earnplus/src/api/api_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import 'test_helpers.dart';

ApiClient clientFor(Map<String, Future<http.Response> Function(http.BaseRequest)> routes,
        {void Function(http.BaseRequest)? onRequest}) =>
    ApiClient(httpClient: FakeHttp.routes(routes, onRequest: onRequest));

Map<String, Future<http.Response> Function(http.BaseRequest)> okRoutes() => {
      'GET /api/config': (_) async => jsonRes(configJson()),
      'GET /api/wallet': (_) async => jsonRes(walletJson()),
      'GET /api/transactions': (_) async => jsonRes(transactionsJson()),
      'GET /api/checkin/status': (_) async => jsonRes(checkinStatusJson()),
      'POST /api/checkin': (_) async => jsonRes({
            'amount': 50,
            'streak': 5,
            'streak_bonus': 10,
            'balance': 1560,
          }),
      'GET /api/spin/status': (_) async => jsonRes(spinStatusJson()),
      'POST /api/spin': (_) async => jsonRes({
            'won': true,
            'amount': 30,
            'segment_index': 2,
            'segments': [10, 20, 30, 50, 100, 0],
            'spins_left': 2,
            'balance': 1590,
          }),
      'POST /api/withdraw/quote': (_) async => jsonRes({
            'coins': 2000,
            'tax_paise': 0,
            'net_display': '₹20',
            'within_limits': true,
          }),
      'POST /api/withdraw': (_) async => jsonRes({
            'id': 42,
            'status': 'pending',
            'message': 'Withdrawal request submitted.',
          }, 201),
    };

void main() {
  group('ApiClient parsing', () {
    test('parses /api/config fully', () async {
      final api = clientFor(okRoutes());
      final c = await api.getConfig();
      expect(c.siteName, 'EarnPlus');
      expect(c.tagline, 'Earn coins, redeem cash');
      expect(c.googleAuthEnabled, isTrue);
      expect(c.emailAuthEnabled, isFalse);
      expect(c.googleClientIdAndroid, 'android-client-id');
      expect(c.coinsPerRupee, 100);
      expect(c.features.spin, isTrue);
      expect(c.features.withdrawals, isTrue);
      expect(c.ads.admob!.rewardedAdUnitId, 'ca-app-pub-test/456');
      expect(c.ads.unity!.gameIdAndroid, '1234567');
      expect(c.ads.unity!.rewardedPlacementId, 'rewardedVideo');
      expect(c.withdraw.presetsPaise, [1000, 2000, 3000]);
      expect(c.withdraw.maxPerDay, 3);
      expect(c.branding.logo, isNull);
    });

    test('config tolerates missing optional sections', () async {
      final api = clientFor({
        'GET /api/config': (_) async => jsonRes({'site_name': 'X'}),
      });
      final c = await api.getConfig();
      expect(c.siteName, 'X');
      expect(c.googleAuthEnabled, isFalse);
      expect(c.ads.admob, isNull);
      expect(c.features.spin, isFalse);
    });

    test('parses /api/wallet', () async {
      final api = clientFor(okRoutes());
      final w = await api.getWallet();
      expect(w.coins, 1500);
      expect(w.rupees, 15.0);
      expect(w.lifetimeEarned, 5000);
    });

    test('parses paginated /api/transactions', () async {
      final api = clientFor(okRoutes());
      final p = await api.getTransactions(page: 1);
      expect(p.data.length, 2);
      expect(p.currentPage, 1);
      expect(p.lastPage, 3);
      expect(p.hasMore, isTrue);
      final first = p.data.first;
      expect(first.amount, 100);
      expect(first.isCredit, isTrue);
      expect(first.source, 'Daily check-in');
      expect(p.data.last.isCredit, isFalse);
    });

    test('parses /api/withdraw/quote', () async {
      final api = clientFor(okRoutes());
      final q = await api.withdrawQuote(1, 2000);
      expect(q.coins, 2000);
      expect(q.taxPaise, 0);
      expect(q.netDisplay, '₹20');
      expect(q.withinLimits, isTrue);
    });

    test('parses POST /api/spin result', () async {
      final api = clientFor(okRoutes());
      final r = await api.doSpin();
      expect(r.won, isTrue);
      expect(r.amount, 30);
      expect(r.segmentIndex, 2);
      expect(r.segments, [10, 20, 30, 50, 100, 0]);
      expect(r.spinsLeft, 2);
      expect(r.balance, 1590);
    });

    test('parses POST /api/checkin result', () async {
      final api = clientFor(okRoutes());
      final r = await api.doCheckin();
      expect(r.amount, 50);
      expect(r.streak, 5);
      expect(r.streakBonus, 10);
      expect(r.balance, 1560);
    });

    test('parses spin status', () async {
      final api = clientFor(okRoutes());
      final s = await api.spinStatus();
      expect(s.enabled, isTrue);
      expect(s.segments, [10, 20, 30, 50, 100, 0]);
      expect(s.spinsLeft, 3);
      expect(s.dailyLimit, 5);
    });

    test('parses checkin status', () async {
      final api = clientFor(okRoutes());
      final s = await api.checkinStatus();
      expect(s.checkedInToday, isFalse);
      expect(s.streak, 4);
    });

    test('withdraw returns 201 result', () async {
      final api = clientFor(okRoutes());
      final r = await api.withdraw(
        methodId: 1,
        amountPaise: 2000,
        idempotencyKey: 'some-uuid',
        details: {'upi_id': 'test@upi'},
      );
      expect(r.id, 42);
      expect(r.status, 'pending');
    });

    test('googleLogin maps token + user', () async {
      final api = clientFor({
        'POST /api/auth/google': (_) async => jsonRes({
              'token': 'tok123',
              'user': userJson(),
            }),
      });
      final r = await api.googleLogin('id-token');
      expect(r.token, 'tok123');
      expect(r.user.name, 'Test User');
      expect(r.user.referralCode, 'TEST123');
    });
  });

  group('ApiClient error mapping', () {
    Future<ApiException> capture(Future<void> Function() fn) async {
      try {
        await fn();
      } on ApiException catch (e) {
        return e;
      }
      fail('expected ApiException');
    }

    test('401 unauthorized', () async {
      final api = clientFor({
        'GET /api/wallet': (_) async =>
            jsonRes({'message': 'Unauthenticated.'}, 401),
      });
      final e = await capture(() => api.getWallet());
      expect(e.statusCode, 401);
      expect(e.isUnauthorized, isTrue);
      expect(e.message, 'Unauthenticated.');
    });

    test('404 feature disabled (spin)', () async {
      final api = clientFor({
        'GET /api/spin/status': (_) async =>
            jsonRes({'message': 'Spins disabled'}, 404),
      });
      final e = await capture(() => api.spinStatus());
      expect(e.statusCode, 404);
      expect(e.isNotFound, isTrue);
    });

    test('409 already checked in', () async {
      final api = clientFor({
        'POST /api/checkin': (_) async =>
            jsonRes({'message': 'Already checked in today'}, 409),
      });
      final e = await capture(() => api.doCheckin());
      expect(e.statusCode, 409);
      expect(e.isConflict, isTrue);
    });

    test('422 validation errors', () async {
      final api = clientFor({
        'POST /api/auth/login': (_) async => jsonRes({
              'message': 'The provided credentials are incorrect.',
              'errors': {
                'email': ['The provided credentials are incorrect.']
              }
            }, 422),
      });
      final e = await capture(() => api.emailLogin('a@b.c', 'wrong'));
      expect(e.statusCode, 422);
      expect(e.isValidation, isTrue);
      expect(e.message, contains('credentials'));
    });

    test('429 rate limited', () async {
      final api = clientFor({
        'POST /api/spin': (_) async =>
            jsonRes({'message': 'Too many attempts'}, 429),
      });
      final e = await capture(() => api.doSpin());
      expect(e.statusCode, 429);
      expect(e.isRateLimited, isTrue);
    });

    test('500 server error message', () async {
      final api = clientFor({
        'GET /api/config': (_) async =>
            http.Response('Internal Server Error', 500),
      });
      final e = await capture(() => api.getConfig());
      expect(e.statusCode, 500);
    });
  });

  group('ApiClient auth header', () {
    test('sends Bearer token on authenticated calls', () async {
      String? authHeader;
      final api = clientFor(
        {'GET /api/wallet': (_) async => jsonRes(walletJson())},
        onRequest: (req) => authHeader = req.headers['Authorization'],
      );
      api.setToken('secret-token');
      await api.getWallet();
      expect(authHeader, 'Bearer secret-token');
    });

    test('no auth header when token unset', () async {
      String? authHeader = 'unset';
      final api = clientFor(
        {'GET /api/config': (_) async => jsonRes(configJson())},
        onRequest: (req) => authHeader = req.headers['Authorization'],
      );
      await api.getConfig();
      expect(authHeader, isNull);
    });

    test('withdraw posts idempotency key + details', () async {
      Map<String, dynamic>? body;
      final api = clientFor({
        'POST /api/withdraw': (req) async {
          body = jsonDecode((req as http.Request).body)
              as Map<String, dynamic>;
          return jsonRes({'id': 1, 'status': 'pending'}, 201);
        },
      });
      await api.withdraw(
        methodId: 2,
        amountPaise: 3000,
        idempotencyKey: 'uuid-1234',
        details: {'account_no': '123'},
      );
      expect(body!['idempotency_key'], 'uuid-1234');
      expect(body!['method_id'], 2);
      expect(body!['amount'], 3000);
      expect((body!['details'] as Map)['account_no'], '123');
    });
  });
}
