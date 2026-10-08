import 'package:earnplus/src/ads/interstitial_scheduler.dart';
import 'package:earnplus/src/api/models.dart';
import 'package:earnplus/src/screens/withdraw_receipt_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

void main() {
  group('WithdrawReceiptScreen', () {
    Future<void> pump(WidgetTester t) => t.pumpWidget(
          Provider<InterstitialScheduler>.value(
            value: InterstitialScheduler(
              interval: const Duration(minutes: 10),
              showAd: () async => false,
            ),
            child: MaterialApp(
              home: Builder(
              builder: (context) => Scaffold(
                body: Center(
                  child: ElevatedButton(
                    key: const Key('open_receipt'),
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const WithdrawReceiptScreen(
                          result: WithdrawResult(
                              id: 42,
                              status: 'pending',
                              message: 'Request received!'),
                          methodName: 'UPI',
                          amountDisplay: '₹15',
                          destination: 'user@upi',
                          coinsDebited: '1500 coins',
                        ),
                      ),
                    ),
                    child: const Text('Open'),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
    testWidgets('renders amount, destination and request id', (t) async {
      await pump(t);
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('open_receipt')));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('receipt_amount')), findsOneWidget);
      expect(find.text('₹15'), findsOneWidget);
      expect(find.text('user@upi'), findsOneWidget);
      expect(find.text('#42'), findsOneWidget);
      expect(find.text('1500 coins'), findsOneWidget);
      expect(find.text('Request received!'), findsOneWidget);
    });

    testWidgets('done button pops back to previous screen', (t) async {
      await pump(t);
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('open_receipt')));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('receipt_amount')), findsOneWidget);
      // The Done button is below the fold (ListView builds lazily):
      // scroll it into view first.
      await t.drag(find.byType(Scrollable).first, const Offset(0, -2000));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('receipt_done')));
      await t.pumpAndSettle();
      // Back on the opener screen: the receipt is gone.
      expect(find.byKey(const Key('receipt_amount')), findsNothing);
      expect(find.byKey(const Key('open_receipt')), findsOneWidget);
    });
  });
}
