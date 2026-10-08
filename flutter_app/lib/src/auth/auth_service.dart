/// Authentication state: secure token storage + signed-in user.
///
/// The bearer token lives in [FlutterSecureStorage] (encrypted on device).
/// Nothing else in the app touches the token directly — screens call this
/// service, which forwards the token to [ApiClient].
library;

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../api/api_client.dart';
import '../api/models.dart';

class AuthService extends ChangeNotifier {
  static const _tokenKey = 'earnplus_auth_token';

  final ApiClient api;
  final FlutterSecureStorage storage;

  User? _user;
  bool _initialized = false;
  bool _busy = false;

  AuthService({required this.api, FlutterSecureStorage? storage})
      : storage = storage ?? const FlutterSecureStorage();

  User? get user => _user;
  bool get isLoggedIn => _user != null;
  bool get initialized => _initialized;
  bool get busy => _busy;

  /// Load a stored token (if any) on app start and validate it via /me.
  Future<void> restoreSession() async {
    final token = await storage.read(key: _tokenKey);
    if (token != null && token.isNotEmpty) {
      api.setToken(token);
      try {
        _user = await api.me();
      } catch (_) {
        // Token invalid/expired — drop it silently, user lands on login.
        await storage.delete(key: _tokenKey);
        api.setToken(null);
        _user = null;
      }
    }
    _initialized = true;
    notifyListeners();
  }

  Future<void> _setBusy(bool v) async {
    _busy = v;
    notifyListeners();
  }

  Future<User> loginWithGoogle(String idToken, {String? ref}) async {
    await _setBusy(true);
    try {
      final result = await api.googleLogin(idToken, ref: ref);
      await _persist(result);
      return result.user;
    } finally {
      await _setBusy(false);
    }
  }

  Future<User> loginWithEmail(String email, String password) async {
    await _setBusy(true);
    try {
      final result = await api.emailLogin(email, password);
      await _persist(result);
      return result.user;
    } finally {
      await _setBusy(false);
    }
  }

  Future<void> _persist(AuthResult result) async {
    await storage.write(key: _tokenKey, value: result.token);
    api.setToken(result.token);
    _user = result.user;
    notifyListeners();
  }

  /// Refresh the cached user (e.g. after wallet changes elsewhere).
  Future<void> refreshUser() async {
    if (!isLoggedIn) return;
    try {
      _user = await api.me();
      notifyListeners();
    } catch (_) {
      // Keep the stale user; a later call will surface the real error.
    }
  }

  void updateUserBalances({required int coins, required double rupees}) {
    if (_user == null) return;
    _user = _user!.copyWith(coins: coins, rupees: rupees);
    notifyListeners();
  }

  Future<void> logout() async {
    await _setBusy(true);
    try {
      try {
        await api.logout();
      } catch (_) {
        // Server logout failing must not trap the user in the app.
      }
      await storage.delete(key: _tokenKey);
      api.setToken(null);
      _user = null;
    } finally {
      await _setBusy(false);
    }
  }
}
