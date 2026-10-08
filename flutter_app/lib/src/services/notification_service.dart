/// In-app notification inbox.
///
/// BACKEND STATUS: the Laravel backend exposes NO notification endpoints
/// for mobile yet (push is a documented stub — see lib/src/push/).
/// This service is the single inbox store: screens read from it, and any
/// future source (push handler, `GET /api/notifications` polling) only
/// needs to call [add] — no UI changes required.
///
/// INTEGRATION POINT: when the backend ships notifications, call
/// `NotificationService.add(...)` from the push-message handler and/or a
/// periodic sync, then delete the local welcome message below.
library;

import 'package:flutter/foundation.dart';

class AppNotification {
  final String id;
  final String title;
  final String body;
  final DateTime at;
  final bool system;
  bool read;

  AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.at,
    this.system = false,
    this.read = false,
  });
}

class NotificationService extends ChangeNotifier {
  final List<AppNotification> _items = [];
  bool _welcomed = false;

  List<AppNotification> get items => List.unmodifiable(_items);
  int get unreadCount => _items.where((n) => !n.read).length;

  /// Adds a notification to the top of the inbox.
  void add(AppNotification n) {
    _items.removeWhere((e) => e.id == n.id);
    _items.insert(0, n);
    notifyListeners();
  }

  /// One locally-generated welcome message so first-time users see how the
  /// inbox works. Clearly marked as coming from the app itself.
  void ensureWelcome() {
    if (_welcomed) return;
    _welcomed = true;
    add(AppNotification(
      id: 'welcome',
      title: 'Welcome to EarnPlus!',
      body:
          'Complete tasks, spin daily and check in to earn coins. Withdrawals open once you reach the minimum.',
      at: DateTime.now(),
      system: true,
    ));
  }

  void markRead(String id) {
    final i = _items.indexWhere((e) => e.id == id);
    if (i >= 0 && !_items[i].read) {
      _items[i].read = true;
      notifyListeners();
    }
  }

  void markAllRead() {
    var changed = false;
    for (final n in _items) {
      if (!n.read) {
        n.read = true;
        changed = true;
      }
    }
    if (changed) notifyListeners();
  }

  void clear() {
    _items.clear();
    notifyListeners();
  }
}
