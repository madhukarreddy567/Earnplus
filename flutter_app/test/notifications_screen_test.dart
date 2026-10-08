import 'package:earnplus/src/screens/notifications_screen.dart';
import 'package:earnplus/src/services/notification_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _wrap(NotificationService inbox) =>
    ChangeNotifierProvider<NotificationService>.value(
      value: inbox,
      child: const MaterialApp(home: NotificationsScreen()),
    );

void main() {
  group('NotificationsScreen', () {
    testWidgets('shows welcome message and unread dot', (t) async {
      final inbox = NotificationService()..ensureWelcome();
      await t.pumpWidget(_wrap(inbox));
      await t.pumpAndSettle();
      expect(find.text('Welcome to EarnPlus!'), findsOneWidget);
      expect(find.byKey(const Key('mark_all_read')), findsOneWidget);
    });

    testWidgets('mark all read clears the action', (t) async {
      final inbox = NotificationService()..ensureWelcome();
      await t.pumpWidget(_wrap(inbox));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('mark_all_read')));
      await t.pumpAndSettle();
      expect(inbox.unreadCount, 0);
      expect(find.byKey(const Key('mark_all_read')), findsNothing);
    });

    testWidgets('tapping a notification marks it read', (t) async {
      final inbox = NotificationService()..ensureWelcome();
      await t.pumpWidget(_wrap(inbox));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('notification_welcome')));
      await t.pumpAndSettle();
      expect(inbox.unreadCount, 0);
    });

    testWidgets('empty inbox shows caught-up state', (t) async {
      final inbox = NotificationService()..clear();
      await t.pumpWidget(_wrap(inbox));
      await t.pumpAndSettle();
      expect(find.textContaining("You're all caught up"), findsOneWidget);
    });
  });
}
