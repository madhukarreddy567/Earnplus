import 'package:earnplus/src/api/api_client.dart';
import 'package:earnplus/src/screens/promotions_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import 'test_helpers.dart';

Widget _wrap(ApiClient api) => MultiProvider(
      providers: [Provider<ApiClient>.value(value: api)],
      child: const MaterialApp(home: PromotionsScreen()),
    );

void main() {
  group('PromotionsScreen', () {
    testWidgets('renders promotion cards with multiplier + badge', (t) async {
      final api = ApiClient(
          httpClient: FakeHttp.routes({
        'GET /api/promotions': (_) async => jsonRes(promotionsJson()),
      }));
      await t.pumpWidget(_wrap(api));
      await t.pumpAndSettle();
      expect(find.text('2x coins on all tasks!'), findsWidgets); // ticker + card
      expect(find.text('HOT'), findsOneWidget);
      expect(find.byKey(const Key('promo_multiplier_Diwali Dhamaka')),
          findsOneWidget);
      expect(find.text('2x'), findsOneWidget);
    });

    testWidgets('shows empty state when no promotions', (t) async {
      final api = ApiClient(
          httpClient: FakeHttp.routes({
        'GET /api/promotions': (_) async => jsonRes({'data': []}),
      }));
      await t.pumpWidget(_wrap(api));
      await t.pumpAndSettle();
      expect(find.textContaining('No active promotions'), findsOneWidget);
    });

    testWidgets('shows error + retry on failure', (t) async {
      final api = ApiClient(
          httpClient: FakeHttp.routes({
        'GET /api/promotions': (_) async =>
            http.Response('boom', 500),
      }));
      await t.pumpWidget(_wrap(api));
      await t.pumpAndSettle();
      expect(find.text('Try again'), findsOneWidget);
    });
  });
}
