import 'dart:convert';

import 'package:earnplus/src/api/api_client.dart';
import 'package:earnplus/src/ads/interstitial_scheduler.dart';
import 'package:earnplus/src/auth/auth_service.dart';
import 'package:earnplus/src/config/app_config.dart';
import 'package:earnplus/src/screens/home_screen.dart';
import 'package:earnplus/src/screens/login_screen.dart';
import 'package:earnplus/src/screens/withdraw_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import 'test_helpers.dart';

/// Builds the provider tree every screen under test expects.
Future<Widget> appWith({
  required ApiClient api,
  required Widget home,
  bool googleAuth = true,
  bool emailAuth = false,
}) async {
  final auth = AuthService(api: api, storage: FakeSecureStorage());
  // Prime the config service through the fake backend.
  final configApi = ApiClient(
    httpClient: FakeHttp.routes({
      'GET /api/config': (_) async =>
          jsonRes(configJson(googleAuth: googleAuth, emailAuth: emailAuth)),
    }),
  );
  final primed = AppConfigService(api: configApi);
  await primed.load();
  return MultiProvider(
    providers: [
      Provider<ApiClient>.value(value: api),
      ChangeNotifierProvider<AppConfigService>.value(value: primed),
      ChangeNotifierProvider<AuthService>.value(value: auth),
      Provider<InterstitialScheduler>.value(
        value: InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async => false,
        ),
      ),
    ],
    child: MaterialApp(home: home),
  );
}

ApiClient screenApi(
        Map<String, Future<http.Response> Function(http.BaseRequest)> routes) =>
    ApiClient(httpClient: FakeHttp.routes(routes));

/// Drags the first ListView down until [target] is visible (max 6 drags).
Future<void> scrollTo(WidgetTester t, Finder target) async {
  for (var i = 0;
      i < 6 && target.evaluate().isEmpty;
      i++) {
    await t.drag(find.byType(ListView).first, const Offset(0, -700));
    await t.pumpAndSettle();
  }
  expect(target, findsWidgets);
}

void main() {
  group('LoginScreen', () {
    testWidgets('Google button shown when google auth enabled', (t) async {
      final api = screenApi({});
      await t.pumpWidget(await appWith(api: api, home: const LoginScreen()));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('google_login_button')), findsOneWidget);
    });

    testWidgets('Google button hidden when google auth disabled', (t) async {
      final api = screenApi({});
      await t.pumpWidget(await appWith(
          api: api, home: const LoginScreen(), googleAuth: false));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('google_login_button')), findsNothing);
    });

    testWidgets('email form hidden when email auth off (default)', (t) async {
      final api = screenApi({});
      await t.pumpWidget(await appWith(api: api, home: const LoginScreen()));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('email_field')), findsNothing);
      expect(find.byKey(const Key('password_field')), findsNothing);
      expect(find.byKey(const Key('email_login_button')), findsNothing);
    });

    testWidgets('email form shown when email auth enabled', (t) async {
      final api = screenApi({});
      await t.pumpWidget(await appWith(
          api: api, home: const LoginScreen(), emailAuth: true));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('email_field')), findsOneWidget);
      expect(find.byKey(const Key('password_field')), findsOneWidget);
      expect(find.byKey(const Key('email_login_button')), findsOneWidget);
    });

    testWidgets('site name + tagline render from config', (t) async {
      final api = screenApi({});
      await t.pumpWidget(await appWith(api: api, home: const LoginScreen()));
      await t.pumpAndSettle();
      expect(find.text('EarnPlus'), findsOneWidget);
      expect(find.text('Earn coins, redeem cash'), findsOneWidget);
    });
  });

  group('HomeScreen', () {
    ApiClient homeApi() => screenApi({
          'GET /api/wallet': (_) async => jsonRes(walletJson()),
          'GET /api/transactions': (_) async => jsonRes(transactionsJson()),
          'GET /api/promotions': (_) async => jsonRes(promotionsJson()),
        });

    testWidgets('balance hero renders coins + rupees', (t) async {
      await t.pumpWidget(
          await appWith(api: homeApi(), home: const HomeScreen()));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('balance_coins')), findsOneWidget);
      expect(find.text('1500 coins'), findsOneWidget);
      expect(find.text('≈ ₹15.00'), findsOneWidget);
    });

    testWidgets('quick actions all present', (t) async {
      await t.pumpWidget(
          await appWith(api: homeApi(), home: const HomeScreen()));
      await t.pumpAndSettle();
      for (final label in ['Tasks', 'Spin', 'Check-in', 'Wallet', 'Withdraw']) {
        expect(find.byKey(Key('quick_$label')), findsOneWidget,
            reason: 'quick action $label missing');
      }
    });

    testWidgets('promo banner renders with badge + multiplier', (t) async {
      await t.pumpWidget(
          await appWith(api: homeApi(), home: const HomeScreen()));
      await t.pumpAndSettle();
      // Below the fold in the test viewport → match offstage widgets too.
      expect(find.text('2x coins on all tasks!', skipOffstage: false),
          findsOneWidget);
      expect(find.text('HOT', skipOffstage: false), findsOneWidget);
      expect(find.text('2.0x', skipOffstage: false), findsOneWidget);
    });

    testWidgets('recent transactions list renders', (t) async {
      await t.pumpWidget(
          await appWith(api: homeApi(), home: const HomeScreen()));
      await t.pumpAndSettle();
      await scrollTo(t, find.text('Daily check-in'));
      expect(find.text('Daily check-in'), findsOneWidget);
      expect(find.text('Withdrawal'), findsOneWidget);
    });

    testWidgets('bottom nav switches tabs', (t) async {
      final api = screenApi({
        'GET /api/wallet': (_) async => jsonRes(walletJson()),
        'GET /api/transactions': (_) async => jsonRes(transactionsJson()),
        'GET /api/promotions': (_) async => jsonRes(promotionsJson()),
        'GET /api/tasks/providers': (_) async => jsonRes({'data': []}),
        'GET /api/referral': (_) async => jsonRes({
              'code': 'TEST123',
              'referred_count': 2,
              'bonus_coins': 100,
              'share_text': 'Join!',
            }),
      });
      await t.pumpWidget(await appWith(api: api, home: const HomeScreen()));
      await t.pumpAndSettle();
      // The bottom-nav label is distinct from the quick-action tile label.
      await t.tap(find.descendant(
        of: find.byType(NavigationBar),
        matching: find.text('Tasks'),
      ));
      await t.pumpAndSettle();
      expect(
          find.text('No task providers available right now.'), findsOneWidget);
    });
  });

  group('WithdrawScreen', () {
    late List<Map<String, dynamic>> quoteRequests;
    late List<Map<String, dynamic>> withdrawRequests;

    ApiClient withdrawApi() {
      quoteRequests = [];
      withdrawRequests = [];
      return screenApi({
        'GET /api/withdraw/methods': (_) async =>
            jsonRes(withdrawMethodsJson()),
        'GET /api/withdrawals': (_) async => jsonRes({'data': []}),
        'POST /api/withdraw/quote': (req) async {
          quoteRequests.add(
              jsonDecode((req as http.Request).body) as Map<String, dynamic>);
          return jsonRes({
            'coins': 2000,
            'tax_paise': 0,
            'net_display': '₹20',
            'within_limits': true,
          });
        },
        'POST /api/withdraw': (req) async {
          withdrawRequests.add(
              jsonDecode((req as http.Request).body) as Map<String, dynamic>);
          return jsonRes(
              {'id': 9, 'status': 'pending', 'message': 'Submitted!'}, 201);
        },
      });
    }

    testWidgets('methods + presets render', (t) async {
      await t.pumpWidget(
          await appWith(api: withdrawApi(), home: const WithdrawScreen()));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('method_1')), findsOneWidget);
      expect(find.byKey(const Key('method_2')), findsOneWidget);
      expect(find.byKey(const Key('preset_1000')), findsOneWidget);
      expect(find.byKey(const Key('preset_2000')), findsOneWidget);
      expect(find.byKey(const Key('preset_3000')), findsOneWidget);
      expect(find.text('₹10'), findsWidgets);
    });

    testWidgets('default preset auto-fetches quote with 1000 paise',
        (t) async {
      await t.pumpWidget(
          await appWith(api: withdrawApi(), home: const WithdrawScreen()));
      await t.pumpAndSettle();
      expect(quoteRequests, isNotEmpty);
      expect(quoteRequests.first['amount'], 1000);
      expect(quoteRequests.first['method_id'], 1);
      expect(find.text('Quote preview'), findsOneWidget);
    });

    testWidgets('tapping ₹20 preset re-quotes with 2000 paise', (t) async {
      await t.pumpWidget(
          await appWith(api: withdrawApi(), home: const WithdrawScreen()));
      await t.pumpAndSettle();
      quoteRequests.clear();
      await t.tap(find.byKey(const Key('preset_2000')));
      await t.pumpAndSettle();
      expect(quoteRequests, isNotEmpty);
      expect(quoteRequests.last['amount'], 2000);
    });

    testWidgets('method detail fields render per type', (t) async {
      await t.pumpWidget(
          await appWith(api: withdrawApi(), home: const WithdrawScreen()));
      await t.pumpAndSettle();
      // UPI selected by default → upi_id field.
      expect(find.byKey(const Key('detail_upi_id')), findsOneWidget);
      // Switch to Bank Transfer → bank fields.
      await t.tap(find.byKey(const Key('method_2')));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('detail_account_no')), findsOneWidget);
      // The IFSC field starts below the fold in the test viewport.
      await scrollTo(t, find.byKey(const Key('detail_ifsc')));
    });

    testWidgets('submit asks for confirmation, then sends idempotency key',
        (t) async {
      await t.pumpWidget(
          await appWith(api: withdrawApi(), home: const WithdrawScreen()));
      await t.pumpAndSettle();
      await t.enterText(find.byKey(const Key('detail_upi_id')), 'test@upi');
      await t.pumpAndSettle();
      final submit = find.byKey(const Key('withdraw_submit_button'));
      await scrollTo(t, submit);
      expect(t.widget<ElevatedButton>(submit).onPressed, isNotNull);
      await t.tap(submit);
      await t.pumpAndSettle();
      // Confirmation dialog first — nothing sent yet.
      expect(withdrawRequests, isEmpty);
      expect(find.byKey(const Key('withdraw_confirm_dialog')), findsOneWidget);
      expect(find.text('test@upi'), findsWidgets); // field + dialog row
      await t.tap(find.byKey(const Key('withdraw_confirm_button')));
      await t.pumpAndSettle();
      expect(withdrawRequests, hasLength(1));
      final body = withdrawRequests.first;
      expect(body['idempotency_key'], isNotNull);
      expect(
          RegExp(r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
              .hasMatch(body['idempotency_key'] as String),
          isTrue,
          reason: 'idempotency key must be UUID v4');
      expect((body['details'] as Map)['upi_id'], 'test@upi');
      // Success now lands on the receipt screen.
      expect(find.byKey(const Key('receipt_amount')), findsOneWidget);
      expect(find.text('Submitted!'), findsOneWidget);
      expect(find.text('#9'), findsOneWidget);
    });

    testWidgets('cancelling confirmation sends nothing', (t) async {
      await t.pumpWidget(
          await appWith(api: withdrawApi(), home: const WithdrawScreen()));
      await t.pumpAndSettle();
      await t.enterText(find.byKey(const Key('detail_upi_id')), 'test@upi');
      await t.pumpAndSettle();
      await scrollTo(t, find.byKey(const Key('withdraw_submit_button')));
      await t.tap(find.byKey(const Key('withdraw_submit_button')));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('withdraw_confirm_dialog')), findsOneWidget);
      await t.tap(find.text('Cancel'));
      await t.pumpAndSettle();
      expect(withdrawRequests, isEmpty);
      expect(find.byKey(const Key('withdraw_confirm_dialog')), findsNothing);
    });
  });
}
