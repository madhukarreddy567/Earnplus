import 'package:earnplus/src/api/api_client.dart';
import 'package:earnplus/src/auth/auth_service.dart';
import 'package:flutter_test/flutter_test.dart';

import 'test_helpers.dart';

void main() {
  group('AuthService token storage', () {
    test('restoreSession with no stored token → logged out, initialized',
        () async {
      final storage = FakeSecureStorage();
      final api = ApiClient(httpClient: FakeHttp.routes({}));
      final auth = AuthService(api: api, storage: storage);
      await auth.restoreSession();
      expect(auth.initialized, isTrue);
      expect(auth.isLoggedIn, isFalse);
      expect(auth.user, isNull);
    });

    test('restoreSession with valid token → user loaded', () async {
      final storage = FakeSecureStorage();
      await storage.write(key: 'earnplus_auth_token', value: 'tok-abc');
      final api = ApiClient(
        httpClient: FakeHttp.routes({
          'GET /api/auth/me': (_) async => jsonRes({'user': userJson()}),
        }),
      );
      final auth = AuthService(api: api, storage: storage);
      await auth.restoreSession();
      expect(auth.isLoggedIn, isTrue);
      expect(auth.user!.name, 'Test User');
      expect(auth.user!.coins, 1500);
    });

    test('restoreSession with expired token → token dropped, logged out',
        () async {
      final storage = FakeSecureStorage();
      await storage.write(key: 'earnplus_auth_token', value: 'stale');
      final api = ApiClient(
        httpClient: FakeHttp.routes({
          'GET /api/auth/me': (_) async =>
              jsonRes({'message': 'Unauthenticated.'}, 401),
        }),
      );
      final auth = AuthService(api: api, storage: storage);
      await auth.restoreSession();
      expect(auth.isLoggedIn, isFalse);
      expect(await storage.read(key: 'earnplus_auth_token'), isNull);
    });

    test('loginWithEmail persists token + user', () async {
      final storage = FakeSecureStorage();
      final api = ApiClient(
        httpClient: FakeHttp.routes({
          'POST /api/auth/login': (_) async => jsonRes({
                'token': 'tok-email',
                'user': userJson(),
              }),
        }),
      );
      final auth = AuthService(api: api, storage: storage);
      final user = await auth.loginWithEmail('test@example.com', 'secret');
      expect(user.email, 'test@example.com');
      expect(await storage.read(key: 'earnplus_auth_token'), 'tok-email');
      expect(auth.isLoggedIn, isTrue);
    });

    test('loginWithGoogle persists token + user', () async {
      final storage = FakeSecureStorage();
      final api = ApiClient(
        httpClient: FakeHttp.routes({
          'POST /api/auth/google': (_) async => jsonRes({
                'token': 'tok-google',
                'user': userJson(),
              }),
        }),
      );
      final auth = AuthService(api: api, storage: storage);
      await auth.loginWithGoogle('id-token-xyz');
      expect(await storage.read(key: 'earnplus_auth_token'), 'tok-google');
      expect(auth.user!.referralCode, 'TEST123');
    });

    test('busy flag toggles during login', () async {
      final storage = FakeSecureStorage();
      final api = ApiClient(
        httpClient: FakeHttp.routes({
          'POST /api/auth/login': (_) async {
            await Future<void>.delayed(const Duration(milliseconds: 20));
            return jsonRes({'token': 't', 'user': userJson()});
          },
        }),
      );
      final auth = AuthService(api: api, storage: storage);
      var sawBusy = false;
      auth.addListener(() {
        if (auth.busy) sawBusy = true;
      });
      await auth.loginWithEmail('a@b.c', 'x');
      expect(sawBusy, isTrue);
      expect(auth.busy, isFalse);
    });

    test('logout clears token + user even when server logout fails',
        () async {
      final storage = FakeSecureStorage();
      await storage.write(key: 'earnplus_auth_token', value: 'tok');
      final api = ApiClient(
        httpClient: FakeHttp.routes({
          'POST /api/auth/logout': (_) async =>
              jsonRes({'message': 'boom'}, 500),
          'GET /api/auth/me': (_) async => jsonRes({'user': userJson()}),
        }),
      );
      final auth = AuthService(api: api, storage: storage);
      await auth.restoreSession();
      expect(auth.isLoggedIn, isTrue);
      await auth.logout();
      expect(auth.isLoggedIn, isFalse);
      expect(await storage.read(key: 'earnplus_auth_token'), isNull);
    });

    test('updateUserBalances updates cached user', () async {
      final storage = FakeSecureStorage();
      final api = ApiClient(
        httpClient: FakeHttp.routes({
          'GET /api/auth/me': (_) async => jsonRes({'user': userJson()}),
        }),
      );
      await storage.write(key: 'earnplus_auth_token', value: 'tok');
      final auth = AuthService(api: api, storage: storage);
      await auth.restoreSession();
      auth.updateUserBalances(coins: 9999, rupees: 99.99);
      expect(auth.user!.coins, 9999);
      expect(auth.user!.rupees, 99.99);
      expect(auth.user!.name, 'Test User'); // other fields preserved
    });

    test('refreshUser updates from /me', () async {
      final storage = FakeSecureStorage();
      var coins = 1500;
      final api = ApiClient(
        httpClient: FakeHttp.routes({
          'GET /api/auth/me': (_) async => jsonRes({
                'user': {...userJson(), 'coins': coins}
              }),
        }),
      );
      await storage.write(key: 'earnplus_auth_token', value: 'tok');
      final auth = AuthService(api: api, storage: storage);
      await auth.restoreSession();
      expect(auth.user!.coins, 1500);
      coins = 2000;
      await auth.refreshUser();
      expect(auth.user!.coins, 2000);
    });
  });
}
