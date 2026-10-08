import 'package:earnplus/src/screens/help_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('HelpScreen', () {
    testWidgets('lists all four policy pages', (t) async {
      await t.pumpWidget(const MaterialApp(home: HelpScreen()));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('policy_terms')), findsOneWidget);
      expect(find.byKey(const Key('policy_privacy')), findsOneWidget);
      expect(find.byKey(const Key('policy_refund')), findsOneWidget);
      expect(find.byKey(const Key('policy_about')), findsOneWidget);
      expect(find.text('Terms of Service'), findsOneWidget);
      expect(find.text('Privacy Policy'), findsOneWidget);
    });

    testWidgets('shows earning tips section', (t) async {
      await t.pumpWidget(const MaterialApp(home: HelpScreen()));
      await t.pumpAndSettle();
      expect(find.text('Earning tips'), findsOneWidget);
      expect(find.textContaining('Check in every day'), findsOneWidget);
    });
  });
}
