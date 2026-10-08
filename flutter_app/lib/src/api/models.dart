/// Typed models for every backend endpoint response.
/// All parsing is defensive: missing keys fall back to sensible defaults so a
/// partial backend response never crashes the app.
library;

int _asInt(dynamic v, [int fallback = 0]) {
  if (v is int) return v;
  if (v is num) return v.toInt();
  if (v is String) return int.tryParse(v) ?? fallback;
  return fallback;
}

double _asDouble(dynamic v, [double fallback = 0]) {
  if (v is num) return v.toDouble();
  if (v is String) return double.tryParse(v) ?? fallback;
  return fallback;
}

String _asString(dynamic v, [String fallback = '']) =>
    v == null ? fallback : v.toString();

bool _asBool(dynamic v, [bool fallback = false]) {
  if (v is bool) return v;
  if (v is String) {
    final s = v.toLowerCase();
    return s == 'true' || s == '1' || s == 'yes';
  }
  if (v is num) return v != 0;
  return fallback;
}

Map<String, dynamic> _asMap(dynamic v) =>
    v is Map<String, dynamic> ? v : (v is Map ? Map<String, dynamic>.from(v) : {});

List<dynamic> _asList(dynamic v) => v is List ? v : const [];

// ---------------------------------------------------------------------------
// /api/config
// ---------------------------------------------------------------------------

class FeatureFlags {
  final bool spin;
  final bool checkin;
  final bool referrals;
  final bool offerwalls;
  final bool ads;
  final bool promotions;
  final bool withdrawals;

  const FeatureFlags({
    this.spin = false,
    this.checkin = false,
    this.referrals = false,
    this.offerwalls = false,
    this.ads = false,
    this.promotions = false,
    this.withdrawals = false,
  });

  factory FeatureFlags.fromJson(Map<String, dynamic> j) => FeatureFlags(
        spin: _asBool(j['spin']),
        checkin: _asBool(j['checkin']),
        referrals: _asBool(j['referrals']),
        offerwalls: _asBool(j['offerwalls']),
        ads: _asBool(j['ads']),
        promotions: _asBool(j['promotions']),
        withdrawals: _asBool(j['withdrawals']),
      );
}

class Branding {
  final String? logo;
  final String? banner;
  final String? banner1200;
  final String? banner768;
  final String? avatar;

  const Branding({this.logo, this.banner, this.banner1200, this.banner768, this.avatar});

  factory Branding.fromJson(Map<String, dynamic> j) => Branding(
        logo: j['logo'] as String?,
        banner: j['banner'] as String?,
        banner1200: j['banner_1200'] as String?,
        banner768: j['banner_768'] as String?,
        avatar: j['avatar'] as String?,
      );
}

class AdMobConfig {
  final String appId;
  final String rewardedAdUnitId;

  const AdMobConfig({required this.appId, required this.rewardedAdUnitId});

  factory AdMobConfig.fromJson(Map<String, dynamic> j) => AdMobConfig(
        appId: _asString(j['app_id']),
        rewardedAdUnitId: _asString(j['rewarded_ad_unit_id']),
      );
}

class UnityConfig {
  final String gameIdAndroid;
  final String gameIdIos;
  final String rewardedPlacementId;
  final String interstitialPlacementId;

  const UnityConfig({
    required this.gameIdAndroid,
    required this.gameIdIos,
    required this.rewardedPlacementId,
    this.interstitialPlacementId = '',
  });

  factory UnityConfig.fromJson(Map<String, dynamic> j) => UnityConfig(
        gameIdAndroid: _asString(j['game_id_android']),
        gameIdIos: _asString(j['game_id_ios']),
        rewardedPlacementId: _asString(j['rewarded_placement_id']),
        interstitialPlacementId: _asString(j['interstitial_placement_id']),
      );
}

class AdsConfig {
  final AdMobConfig? admob;
  final UnityConfig? unity;

  const AdsConfig({this.admob, this.unity});

  factory AdsConfig.fromJson(Map<String, dynamic> j) => AdsConfig(
        admob: j['admob'] == null ? null : AdMobConfig.fromJson(_asMap(j['admob'])),
        unity: j['unity'] == null ? null : UnityConfig.fromJson(_asMap(j['unity'])),
      );
}

class WithdrawConfig {
  final List<int> presetsPaise;
  final int maxPerDay;

  const WithdrawConfig({this.presetsPaise = const [], this.maxPerDay = 0});

  factory WithdrawConfig.fromJson(Map<String, dynamic> j) => WithdrawConfig(
        presetsPaise: _asList(j['presets_paise']).map(_asInt).toList(),
        maxPerDay: _asInt(j['max_per_day']),
      );
}

class AppConfig {
  final String siteName;
  final String tagline;
  final bool emailAuthEnabled;
  final bool googleAuthEnabled;
  final String? googleClientIdAndroid;
  final String? googleClientIdIos;
  final int coinsPerRupee;
  final FeatureFlags features;
  final Branding branding;
  final AdsConfig ads;
  final WithdrawConfig withdraw;

  const AppConfig({
    required this.siteName,
    this.tagline = '',
    this.emailAuthEnabled = false,
    this.googleAuthEnabled = false,
    this.googleClientIdAndroid,
    this.googleClientIdIos,
    this.coinsPerRupee = 100,
    this.features = const FeatureFlags(),
    this.branding = const Branding(),
    this.ads = const AdsConfig(),
    this.withdraw = const WithdrawConfig(),
  });

  factory AppConfig.fromJson(Map<String, dynamic> j) => AppConfig(
        siteName: _asString(j['site_name'], 'EarnPlus'),
        tagline: _asString(j['tagline']),
        emailAuthEnabled: _asBool(j['email_auth_enabled']),
        googleAuthEnabled: _asBool(j['google_auth_enabled']),
        googleClientIdAndroid: j['google_client_id_android'] as String?,
        googleClientIdIos: j['google_client_id_ios'] as String?,
        coinsPerRupee: _asInt(j['coins_per_rupee'], 100),
        features: FeatureFlags.fromJson(_asMap(j['features'])),
        branding: Branding.fromJson(_asMap(j['branding'])),
        ads: AdsConfig.fromJson(_asMap(j['ads'])),
        withdraw: WithdrawConfig.fromJson(_asMap(j['withdraw'])),
      );
}

// ---------------------------------------------------------------------------
// Auth / user
// ---------------------------------------------------------------------------

class User {
  final int id;
  final String name;
  final String email;
  final String? avatar;
  final String referralCode;
  final int coins;
  final double rupees;

  const User({
    required this.id,
    required this.name,
    this.email = '',
    this.avatar,
    this.referralCode = '',
    this.coins = 0,
    this.rupees = 0,
  });

  factory User.fromJson(Map<String, dynamic> j) => User(
        id: _asInt(j['id']),
        name: _asString(j['name'], 'User'),
        email: _asString(j['email']),
        avatar: j['avatar'] as String?,
        referralCode: _asString(j['referral_code']),
        coins: _asInt(j['coins']),
        rupees: _asDouble(j['rupees']),
      );

  User copyWith({int? coins, double? rupees, String? name, String? avatar}) =>
      User(
        id: id,
        name: name ?? this.name,
        email: email,
        avatar: avatar ?? this.avatar,
        referralCode: referralCode,
        coins: coins ?? this.coins,
        rupees: rupees ?? this.rupees,
      );
}

class AuthResult {
  final String token;
  final User user;

  const AuthResult({required this.token, required this.user});
}

// ---------------------------------------------------------------------------
// Wallet
// ---------------------------------------------------------------------------

class Wallet {
  final int coins;
  final double rupees;
  final int lifetimeEarned;

  const Wallet({this.coins = 0, this.rupees = 0, this.lifetimeEarned = 0});

  factory Wallet.fromJson(Map<String, dynamic> j) => Wallet(
        coins: _asInt(j['coins']),
        rupees: _asDouble(j['rupees']),
        lifetimeEarned: _asInt(j['lifetime_earned']),
      );
}

class TransactionItem {
  final int id;
  final String type;
  final int amount;
  final String source;
  final int balanceAfter;
  final String createdAt;

  const TransactionItem({
    required this.id,
    this.type = '',
    this.amount = 0,
    this.source = '',
    this.balanceAfter = 0,
    this.createdAt = '',
  });

  factory TransactionItem.fromJson(Map<String, dynamic> j) => TransactionItem(
        id: _asInt(j['id']),
        type: _asString(j['type']),
        amount: _asInt(j['amount']),
        source: _asString(j['source']),
        balanceAfter: _asInt(j['balance_after']),
        createdAt: _asString(j['created_at']),
      );

  bool get isCredit => amount >= 0;
}

class Paginated<T> {
  final List<T> data;
  final int currentPage;
  final int lastPage;

  const Paginated({this.data = const [], this.currentPage = 1, this.lastPage = 1});

  bool get hasMore => currentPage < lastPage;
}

// ---------------------------------------------------------------------------
// Check-in
// ---------------------------------------------------------------------------

class CheckinStatus {
  final bool checkedInToday;
  final int streak;

  const CheckinStatus({this.checkedInToday = false, this.streak = 0});

  factory CheckinStatus.fromJson(Map<String, dynamic> j) => CheckinStatus(
        checkedInToday: _asBool(j['checked_in_today']),
        streak: _asInt(j['streak']),
      );
}

class CheckinResult {
  final int amount;
  final int streak;
  final int streakBonus;
  final int balance;

  const CheckinResult({
    this.amount = 0,
    this.streak = 0,
    this.streakBonus = 0,
    this.balance = 0,
  });

  factory CheckinResult.fromJson(Map<String, dynamic> j) => CheckinResult(
        amount: _asInt(j['amount']),
        streak: _asInt(j['streak']),
        streakBonus: _asInt(j['streak_bonus']),
        balance: _asInt(j['balance']),
      );
}

// ---------------------------------------------------------------------------
// Spin
// ---------------------------------------------------------------------------

class SpinStatus {
  final bool enabled;
  final List<int> segments;
  final int spinsLeft;
  final int dailyLimit;
  final bool adGateEnabled;

  const SpinStatus({
    this.enabled = false,
    this.segments = const [],
    this.spinsLeft = 0,
    this.dailyLimit = 0,
    this.adGateEnabled = true,
  });

  factory SpinStatus.fromJson(Map<String, dynamic> j) => SpinStatus(
        enabled: _asBool(j['enabled'], true),
        segments: _asList(j['segments']).map(_asInt).toList(),
        spinsLeft: _asInt(j['spins_left']),
        dailyLimit: _asInt(j['daily_limit']),
        adGateEnabled: _asBool(j['ad_gate_enabled'], true),
      );
}

class SpinResult {
  final bool won;
  final int amount;
  final int segmentIndex;
  final List<int> segments;
  final int spinsLeft;
  final int balance;

  /// Present on a win: the single-use token to claim the reward with
  /// (POST /api/spin/claim) after the rewarded-ad gate. Null when the
  /// spin lost or the gate is disabled server-side.
  final String? claimToken;
  final DateTime? claimExpiresAt;

  const SpinResult({
    this.won = false,
    this.amount = 0,
    this.segmentIndex = 0,
    this.segments = const [],
    this.spinsLeft = 0,
    this.balance = 0,
    this.claimToken,
    this.claimExpiresAt,
  });

  factory SpinResult.fromJson(Map<String, dynamic> j) => SpinResult(
        won: _asBool(j['won']),
        amount: _asInt(j['amount']),
        segmentIndex: _asInt(j['segment_index']),
        segments: _asList(j['segments']).map(_asInt).toList(),
        spinsLeft: _asInt(j['spins_left']),
        balance: _asInt(j['balance']),
        claimToken: j['claim_token']?.toString(),
        claimExpiresAt: j['claim_expires_at'] == null
            ? null
            : DateTime.tryParse(j['claim_expires_at'].toString()),
      );
}

/// Result of POST /api/spin/claim.
class SpinClaimResult {
  final int coins;
  final int balance;

  const SpinClaimResult({this.coins = 0, this.balance = 0});

  factory SpinClaimResult.fromJson(Map<String, dynamic> j) => SpinClaimResult(
        coins: _asInt(j['coins']),
        balance: _asInt(j['balance']),
      );
}

/// Pollable status of a pending spin claim (GET /api/spin/claim/{token}).
class SpinClaimStatus {
  final String status;
  final bool adVerified;
  final bool expired;
  final bool claimed;
  final int amount;

  const SpinClaimStatus({
    this.status = 'unknown',
    this.adVerified = false,
    this.expired = false,
    this.claimed = false,
    this.amount = 0,
  });

  factory SpinClaimStatus.fromJson(Map<String, dynamic> j) => SpinClaimStatus(
        status: j['status']?.toString() ?? 'unknown',
        adVerified: _asBool(j['ad_verified']),
        expired: _asBool(j['expired']),
        claimed: _asBool(j['claimed']),
        amount: _asInt(j['amount']),
      );
}

// ---------------------------------------------------------------------------
// Tasks / offerwalls
// ---------------------------------------------------------------------------

class DemoTask {
  final String key;
  final String title;
  final int payoutCoins;

  const DemoTask({this.key = '', this.title = '', this.payoutCoins = 0});

  factory DemoTask.fromJson(Map<String, dynamic> j) => DemoTask(
        key: _asString(j['key']),
        title: _asString(j['title']),
        payoutCoins: _asInt(j['payout_coins']),
      );
}

class TaskProvider {
  final int id;
  final String slug;
  final String name;
  final bool sandbox;
  final String? badge;
  final List<DemoTask> demoTasks;

  const TaskProvider({
    required this.id,
    required this.slug,
    this.name = '',
    this.sandbox = false,
    this.badge,
    this.demoTasks = const [],
  });

  factory TaskProvider.fromJson(Map<String, dynamic> j) => TaskProvider(
        id: _asInt(j['id']),
        slug: _asString(j['slug']),
        name: _asString(j['name']),
        sandbox: _asBool(j['sandbox']),
        badge: j['badge'] as String?,
        demoTasks: _asList(j['demo_tasks'])
            .map((e) => DemoTask.fromJson(_asMap(e)))
            .toList(),
      );
}

// ---------------------------------------------------------------------------
// Promotions
// ---------------------------------------------------------------------------

class Promotion {
  final String name;
  final double multiplier;
  final String scope;
  final String bannerTitle;
  final String bannerSubtitle;
  final String? badge;
  final String? endsAt;

  const Promotion({
    this.name = '',
    this.multiplier = 1,
    this.scope = '',
    this.bannerTitle = '',
    this.bannerSubtitle = '',
    this.badge,
    this.endsAt,
  });

  factory Promotion.fromJson(Map<String, dynamic> j) => Promotion(
        name: _asString(j['name']),
        multiplier: _asDouble(j['multiplier'], 1),
        scope: _asString(j['scope']),
        bannerTitle: _asString(j['banner_title']),
        bannerSubtitle: _asString(j['banner_subtitle']),
        badge: j['badge'] as String?,
        endsAt: j['ends_at'] as String?,
      );
}

// ---------------------------------------------------------------------------
// Withdraw
// ---------------------------------------------------------------------------

class WithdrawMethod {
  final int id;
  final String name;
  final String type; // upi | paytm | bank | paypal_manual
  final String currency;
  final int min;
  final int max;
  final int minAmount;
  final int maxAmount;
  final List<String> detailFields;

  const WithdrawMethod({
    required this.id,
    this.name = '',
    this.type = '',
    this.currency = 'INR',
    this.min = 0,
    this.max = 0,
    this.minAmount = 0,
    this.maxAmount = 0,
    this.detailFields = const [],
  });

  factory WithdrawMethod.fromJson(Map<String, dynamic> j) => WithdrawMethod(
        id: _asInt(j['id']),
        name: _asString(j['name']),
        type: _asString(j['type']),
        currency: _asString(j['currency'], 'INR'),
        min: _asInt(j['min']),
        max: _asInt(j['max']),
        minAmount: _asInt(j['min_amount']),
        maxAmount: _asInt(j['max_amount']),
        detailFields:
            _asList(j['detail_fields']).map((e) => e.toString()).toList(),
      );

  /// Human-friendly field labels for the per-type detail form.
  static const Map<String, Map<String, String>> fieldLabels = {
    'upi': {'upi_id': 'UPI ID'},
    'paytm': {'mobile': 'Paytm Mobile Number'},
    'bank': {
      'account_holder': 'Account Holder Name',
      'account_no': 'Account Number',
      'ifsc': 'IFSC Code',
    },
    'paypal_manual': {
      'paypal_name': 'PayPal Account Name',
      'paypal_email': 'PayPal Email',
    },
  };

  String labelFor(String field) => fieldLabels[type]?[field] ?? field;
}

class WithdrawMethodsResponse {
  final bool enabled;
  final int balanceCoins;
  final int withdrawablePaise;
  final int maxPerDay;
  final int requestsToday;
  final List<int> presetsPaise;
  final List<WithdrawMethod> methods;

  const WithdrawMethodsResponse({
    this.enabled = false,
    this.balanceCoins = 0,
    this.withdrawablePaise = 0,
    this.maxPerDay = 0,
    this.requestsToday = 0,
    this.presetsPaise = const [],
    this.methods = const [],
  });

  factory WithdrawMethodsResponse.fromJson(Map<String, dynamic> j) =>
      WithdrawMethodsResponse(
        enabled: _asBool(j['enabled']),
        balanceCoins: _asInt(j['balance_coins']),
        withdrawablePaise: _asInt(j['withdrawable_paise']),
        maxPerDay: _asInt(j['max_per_day']),
        requestsToday: _asInt(j['requests_today']),
        presetsPaise: _asList(j['presets_paise']).map(_asInt).toList(),
        methods: _asList(j['data'])
            .map((e) => WithdrawMethod.fromJson(_asMap(e)))
            .toList(),
      );
}

class WithdrawQuote {
  final int coins;
  final int taxPaise;
  final String netDisplay;
  final bool withinLimits;

  const WithdrawQuote({
    this.coins = 0,
    this.taxPaise = 0,
    this.netDisplay = '',
    this.withinLimits = false,
  });

  factory WithdrawQuote.fromJson(Map<String, dynamic> j) => WithdrawQuote(
        coins: _asInt(j['coins']),
        taxPaise: _asInt(j['tax_paise']),
        netDisplay: _asString(j['net_display']),
        withinLimits: _asBool(j['within_limits']),
      );
}

class WithdrawResult {
  final int id;
  final String status;
  final String message;

  const WithdrawResult({this.id = 0, this.status = '', this.message = ''});

  factory WithdrawResult.fromJson(Map<String, dynamic> j) => WithdrawResult(
        id: _asInt(j['id']),
        status: _asString(j['status']),
        message: _asString(j['message']),
      );
}

class WithdrawalItem {
  final int id;
  final String method;
  final String amountDisplay;
  final String status;
  final String createdAt;

  const WithdrawalItem({
    required this.id,
    this.method = '',
    this.amountDisplay = '',
    this.status = '',
    this.createdAt = '',
  });

  factory WithdrawalItem.fromJson(Map<String, dynamic> j) => WithdrawalItem(
        id: _asInt(j['id']),
        method: _asString(j['method']),
        amountDisplay: _asString(j['amount_display']),
        status: _asString(j['status']),
        createdAt: _asString(j['created_at']),
      );
}

// ---------------------------------------------------------------------------
// Referral
// ---------------------------------------------------------------------------

class ReferralInfo {
  final String code;
  final int referredCount;
  final int bonusCoins;
  final String shareText;

  const ReferralInfo({
    this.code = '',
    this.referredCount = 0,
    this.bonusCoins = 0,
    this.shareText = '',
  });

  factory ReferralInfo.fromJson(Map<String, dynamic> j) => ReferralInfo(
        code: _asString(j['code']),
        referredCount: _asInt(j['referred_count']),
        bonusCoins: _asInt(j['bonus_coins']),
        shareText: _asString(j['share_text']),
      );
}

// ---------------------------------------------------------------------------
// Ads reward (WebView fallback placement)
// ---------------------------------------------------------------------------

class AdRewardResult {
  final int coins;
  final int balance;

  const AdRewardResult({this.coins = 0, this.balance = 0});

  factory AdRewardResult.fromJson(Map<String, dynamic> j) => AdRewardResult(
        coins: _asInt(j['coins']),
        balance: _asInt(j['balance']),
      );
}
