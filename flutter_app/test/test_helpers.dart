/// Shared fakes + fixtures for EarnPlus tests.
library;

import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

/// In-memory fake for FlutterSecureStorage (no platform channels in tests).
class FakeSecureStorage extends FlutterSecureStorage {
  final Map<String, String> _store = {};

  FakeSecureStorage() : super();

  @override
  Future<String?> read({required String key, IOSOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, MacOsOptions? mOptions, WindowsOptions? wOptions}) async {
    return _store[key];
  }

  @override
  Future<void> write({required String key, required String? value, IOSOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, MacOsOptions? mOptions, WindowsOptions? wOptions}) async {
    if (value == null) {
      _store.remove(key);
    } else {
      _store[key] = value;
    }
  }

  @override
  Future<void> delete({required String key, IOSOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, MacOsOptions? mOptions, WindowsOptions? wOptions}) async {
    _store.remove(key);
  }

  @override
  Future<void> deleteAll({IOSOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, MacOsOptions? mOptions, WindowsOptions? wOptions}) async {
    _store.clear();
  }

  @override
  Future<bool> containsKey({required String key, IOSOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, MacOsOptions? mOptions, WindowsOptions? wOptions}) async {
    return _store.containsKey(key);
  }

  @override
  Future<Map<String, String>> readAll({IOSOptions? iOptions, AndroidOptions? aOptions, LinuxOptions? lOptions, WebOptions? webOptions, MacOsOptions? mOptions, WindowsOptions? wOptions}) async {
    return Map.of(_store);
  }
}

/// Mock http client that routes requests through a handler map.
class FakeHttp extends MockClient {
  FakeHttp(super.handler);

  /// Route table: 'GET /api/config' → handler returning (status, json).
  static FakeHttp routes(
      Map<String, Future<http.Response> Function(http.BaseRequest)> table,
      {void Function(http.BaseRequest)? onRequest}) {
    return FakeHttp((http.BaseRequest req) async {
      onRequest?.call(req);
      final key = '${req.method} ${req.url.path}';
      final handler = table[key];
      if (handler == null) {
        return http.Response(
            jsonEncode({'message': 'No fake route for $key'}), 404);
      }
      return handler(req);
    });
  }
}

http.Response jsonRes(Object body, [int status = 200]) =>
    http.Response(jsonEncode(body), status,
        headers: {'content-type': 'application/json'});

Map<String, dynamic> configJson({
  bool googleAuth = true,
  bool emailAuth = false,
}) =>
    {
      'site_name': 'EarnPlus',
      'tagline': 'Earn coins, redeem cash',
      'email_auth_enabled': emailAuth,
      'google_auth_enabled': googleAuth,
      'google_client_id_android': 'android-client-id',
      'google_client_id_ios': 'ios-client-id',
      'coins_per_rupee': 100,
      'features': {
        'spin': true,
        'checkin': true,
        'referrals': true,
        'offerwalls': true,
        'ads': true,
        'promotions': true,
        'withdrawals': true,
      },
      'branding': {
        'logo': null,
        'banner': null,
        'banner_1200': null,
        'banner_768': null,
        'avatar': null,
      },
      'ads': {
        'admob': {
          'app_id': 'ca-app-pub-test~123',
          'rewarded_ad_unit_id': 'ca-app-pub-test/456',
        },
        'unity': {
          'game_id_android': '1234567',
          'game_id_ios': '7654321',
          'rewarded_placement_id': 'rewardedVideo',
        },
      },
      'withdraw': {
        'presets_paise': [1000, 2000, 3000],
        'max_per_day': 3,
      },
    };

Map<String, dynamic> userJson() => {
      'id': 7,
      'name': 'Test User',
      'email': 'test@example.com',
      'avatar': null,
      'referral_code': 'TEST123',
      'coins': 1500,
      'rupees': 15.0,
    };

Map<String, dynamic> walletJson() => {
      'coins': 1500,
      'rupees': 15.0,
      'lifetime_earned': 5000,
    };

Map<String, dynamic> transactionsJson({int page = 1}) => {
      'data': [
        {
          'id': 1,
          'type': 'credit',
          'amount': 100,
          'source': 'Daily check-in',
          'balance_after': 1500,
          'created_at': '2026-10-07 10:00:00',
        },
        {
          'id': 2,
          'type': 'debit',
          'amount': -200,
          'source': 'Withdrawal',
          'balance_after': 1400,
          'created_at': '2026-10-06 10:00:00',
        },
      ],
      'current_page': page,
      'last_page': 3,
    };

Map<String, dynamic> promotionsJson() => {
      'data': [
        {
          'name': 'Diwali Dhamaka',
          'multiplier': 2.0,
          'scope': 'all',
          'banner_title': '2x coins on all tasks!',
          'banner_subtitle': 'Limited time festival offer',
          'badge': 'HOT',
          'ends_at': '2026-10-20T00:00:00+05:30',
        },
      ],
    };

Map<String, dynamic> withdrawMethodsJson() => {
      'enabled': true,
      'balance_coins': 1500,
      'withdrawable_paise': 1500,
      'max_per_day': 3,
      'requests_today': 0,
      'presets_paise': [1000, 2000, 3000],
      'data': [
        {
          'id': 1,
          'name': 'UPI',
          'type': 'upi',
          'currency': 'INR',
          'min': 1000,
          'max': 100000,
          'min_amount': 1000,
          'max_amount': 100000,
          'detail_fields': ['upi_id'],
        },
        {
          'id': 2,
          'name': 'Bank Transfer',
          'type': 'bank',
          'currency': 'INR',
          'min': 1000,
          'max': 100000,
          'min_amount': 1000,
          'max_amount': 100000,
          'detail_fields': ['account_holder', 'account_no', 'ifsc'],
        },
      ],
    };

Map<String, dynamic> spinStatusJson() => {
      'enabled': true,
      'segments': [10, 20, 30, 50, 100, 0],
      'spins_left': 3,
      'daily_limit': 5,
    };

Map<String, dynamic> checkinStatusJson() => {
      'checked_in_today': false,
      'streak': 4,
    };
