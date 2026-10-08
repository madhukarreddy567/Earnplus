/// Typed HTTP client for the EarnPlus Laravel backend.
///
/// Base URL comes from [apiBaseUrl] in `lib/config.dart` — the single place
/// the owner edits to point the app at staging / production.
///
/// Error handling: every non-2xx response throws [ApiException] carrying the
/// HTTP status and the backend's message, so UI layers can react to
/// 401 (bad/expired token), 404 (feature disabled), 409 (already checked in),
/// 422 (validation), 429 (rate limited) distinctly.
library;

import 'dart:convert';

import 'package:http/http.dart' as http;

import '../../config.dart';
import 'models.dart';

/// Thrown for any non-2xx backend response or transport failure.
class ApiException implements Exception {
  /// HTTP status code, or 0 when the request never reached the server.
  final int statusCode;
  final String message;

  const ApiException(this.statusCode, this.message);

  bool get isUnauthorized => statusCode == 401;
  bool get isNotFound => statusCode == 404;
  bool get isConflict => statusCode == 409;
  bool get isValidation => statusCode == 422;
  bool get isRateLimited => statusCode == 429;

  @override
  String toString() => 'ApiException($statusCode): $message';

  factory ApiException.fromResponse(http.Response res) {
    String message = 'Request failed (${res.statusCode})';
    try {
      final body = jsonDecode(res.body);
      if (body is Map<String, dynamic>) {
        message = body['message']?.toString() ??
            body['error']?.toString() ??
            message;
      }
    } catch (_) {
      if (res.body.isNotEmpty && res.body.length < 200) {
        message = res.body;
      }
    }
    return ApiException(res.statusCode, message);
  }
}

class ApiClient {
  final http.Client _http;
  final String baseUrl;
  String? _token;

  ApiClient({http.Client? httpClient, String? baseUrl})
      : _http = httpClient ?? http.Client(),
        baseUrl = baseUrl ?? apiBaseUrl;

  void setToken(String? token) => _token = token;

  Uri _uri(String path, [Map<String, String>? query]) =>
      Uri.parse('$baseUrl$path').replace(queryParameters: query);

  Map<String, String> _headers({bool json = false}) {
    final h = <String, String>{'Accept': 'application/json'};
    if (json) h['Content-Type'] = 'application/json';
    if (_token != null && _token!.isNotEmpty) {
      h['Authorization'] = 'Bearer $_token';
    }
    return h;
  }

  Map<String, dynamic> _decode(http.Response res) {
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw ApiException.fromResponse(res);
    }
    if (res.body.isEmpty) return {};
    final body = jsonDecode(res.body);
    if (body is Map<String, dynamic>) return body;
    return {'data': body};
  }

  Future<Map<String, dynamic>> _get(String path,
      [Map<String, String>? query]) async {
    try {
      final res = await _http.get(_uri(path, query), headers: _headers());
      return _decode(res);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException(0, 'Network error: $e');
    }
  }

  Future<Map<String, dynamic>> _post(String path,
      [Map<String, dynamic>? body]) async {
    try {
      final res = await _http.post(
        _uri(path),
        headers: _headers(json: true),
        body: body == null ? null : jsonEncode(body),
      );
      return _decode(res);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException(0, 'Network error: $e');
    }
  }

  List<T> _listOf<T>(
      Map<String, dynamic> j, T Function(Map<String, dynamic>) fromJson) {
    final raw = j['data'];
    if (raw is List) {
      return raw
          .whereType<Map<String, dynamic>>()
          .map(fromJson)
          .toList(growable: false);
    }
    return const [];
  }

  // -------------------------------------------------------------------------
  // Public endpoints
  // -------------------------------------------------------------------------

  /// GET /api/config
  Future<AppConfig> getConfig() async {
    final j = await _get('/api/config');
    return AppConfig.fromJson(j);
  }

  /// POST /api/auth/google
  Future<AuthResult> googleLogin(String idToken, {String? ref}) async {
    final j = await _post('/api/auth/google', {
      'id_token': idToken,
      if (ref != null && ref.isNotEmpty) 'ref': ref,
    });
    final token = j['token']?.toString() ?? '';
    if (token.isEmpty) throw const ApiException(500, 'Missing token in response');
    final user = User.fromJson(Map<String, dynamic>.from(j['user'] as Map? ?? {}));
    return AuthResult(token: token, user: user);
  }

  /// POST /api/auth/login
  Future<AuthResult> emailLogin(String email, String password) async {
    final j = await _post('/api/auth/login', {'email': email, 'password': password});
    final token = j['token']?.toString() ?? '';
    if (token.isEmpty) throw const ApiException(500, 'Missing token in response');
    final user = User.fromJson(Map<String, dynamic>.from(j['user'] as Map? ?? {}));
    return AuthResult(token: token, user: user);
  }

  // -------------------------------------------------------------------------
  // Authenticated endpoints (Authorization: Bearer <token>)
  // -------------------------------------------------------------------------

  /// POST /api/auth/logout
  Future<void> logout() async {
    await _post('/api/auth/logout');
  }

  /// GET /api/auth/me
  Future<User> me() async {
    final j = await _get('/api/auth/me');
    final u = j['user'];
    return User.fromJson(
        u is Map<String, dynamic> ? u : Map<String, dynamic>.from(j));
  }

  /// GET /api/wallet
  Future<Wallet> getWallet() async {
    final j = await _get('/api/wallet');
    return Wallet.fromJson(j);
  }

  /// GET /api/transactions?page=N
  Future<Paginated<TransactionItem>> getTransactions({int page = 1}) async {
    final j = await _get('/api/transactions', {'page': page.toString()});
    return Paginated<TransactionItem>(
      data: _listOf(j, TransactionItem.fromJson),
      currentPage: (j['current_page'] as num?)?.toInt() ?? page,
      lastPage: (j['last_page'] as num?)?.toInt() ?? page,
    );
  }

  /// GET /api/checkin/status
  Future<CheckinStatus> checkinStatus() async {
    final j = await _get('/api/checkin/status');
    return CheckinStatus.fromJson(j);
  }

  /// POST /api/checkin — throws ApiException(409) when already checked in.
  Future<CheckinResult> doCheckin() async {
    final j = await _post('/api/checkin');
    return CheckinResult.fromJson(j);
  }

  /// GET /api/spin/status — throws ApiException(404) when spins are disabled.
  Future<SpinStatus> spinStatus() async {
    final j = await _get('/api/spin/status');
    return SpinStatus.fromJson(j);
  }

  /// POST /api/spin — the SERVER decides the outcome; the app only animates
  /// to [SpinResult.segmentIndex]. Never compute winnings client-side.
  /// On a win the server returns a single-use [SpinResult.claimToken]:
  /// no coins are credited until the rewarded-ad gate completes.
  Future<SpinResult> doSpin() async {
    final j = await _post('/api/spin');
    return SpinResult.fromJson(j);
  }

  /// POST /api/spin/claim — credits a pending spin reward. The server
  /// credits only when Unity's S2S callback verified the rewarded-ad
  /// view for [claimToken]. Throws ApiException(422) otherwise.
  Future<SpinClaimResult> claimSpin(String claimToken) async {
    final j = await _post('/api/spin/claim', {'claim_token': claimToken});
    return SpinClaimResult.fromJson(j);
  }

  /// GET /api/spin/claim/{token} — pollable claim status. The app polls
  /// this after the ad closes because Unity's S2S callback arrives
  /// asynchronously. Throws ApiException(404) for unknown tokens.
  Future<SpinClaimStatus> spinClaimStatus(String claimToken) async {
    final j = await _get('/api/spin/claim/$claimToken');
    return SpinClaimStatus.fromJson(j);
  }

  /// GET /api/tasks/providers
  Future<List<TaskProvider>> getTaskProviders() async {
    final j = await _get('/api/tasks/providers');
    return _listOf(j, TaskProvider.fromJson);
  }

  /// POST /api/tasks/click/{slug} — returns the URL to open in a WebView.
  Future<String> taskClickUrl(String slug) async {
    final j = await _post('/api/tasks/click/$slug');
    final url = j['url']?.toString() ?? '';
    if (url.isEmpty) throw const ApiException(500, 'Missing task URL');
    return url;
  }

  /// GET /api/promotions
  Future<List<Promotion>> getPromotions() async {
    final j = await _get('/api/promotions');
    return _listOf(j, Promotion.fromJson);
  }

  /// GET /api/withdraw/methods
  Future<WithdrawMethodsResponse> withdrawMethods() async {
    final j = await _get('/api/withdraw/methods');
    return WithdrawMethodsResponse.fromJson(j);
  }

  /// POST /api/withdraw/quote
  Future<WithdrawQuote> withdrawQuote(int methodId, int amountPaise) async {
    final j = await _post('/api/withdraw/quote', {
      'method_id': methodId,
      'amount': amountPaise,
    });
    return WithdrawQuote.fromJson(j);
  }

  /// POST /api/withdraw — [idempotencyKey] must be a client-generated UUID v4.
  Future<WithdrawResult> withdraw({
    required int methodId,
    required int amountPaise,
    required String idempotencyKey,
    required Map<String, String> details,
  }) async {
    final j = await _post('/api/withdraw', {
      'method_id': methodId,
      'amount': amountPaise,
      'idempotency_key': idempotencyKey,
      'details': details,
    });
    return WithdrawResult.fromJson(j);
  }

  /// GET /api/withdrawals
  Future<List<WithdrawalItem>> getWithdrawals() async {
    final j = await _get('/api/withdrawals');
    return _listOf(j, WithdrawalItem.fromJson);
  }

  /// GET /api/referral
  Future<ReferralInfo> getReferral() async {
    final j = await _get('/api/referral');
    return ReferralInfo.fromJson(j);
  }

  /// POST /api/ads/reward/{placement} — WebView fallback placement reward.
  Future<AdRewardResult> adReward(String placement) async {
    final j = await _post('/api/ads/reward/$placement');
    return AdRewardResult.fromJson(j);
  }

  void close() => _http.close();
}
