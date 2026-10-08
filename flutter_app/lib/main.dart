/// EarnPlus — entry point.
///
/// Startup order:
///   1. Build the [ApiClient] (base URL from lib/config.dart).
///   2. Provide [AppConfigService] + [AuthService].
///   3. Splash loads /api/config, restores the session, then initializes
///      ads (AdMob + Unity) with the backend-supplied ids.
///   4. PushService is intentionally NEVER initialized (no Firebase setup —
///      see lib/src/push/push_service.dart and lib/README.md).
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config.dart';
import 'src/ads/ad_service.dart';
import 'src/ads/interstitial_scheduler.dart';
import 'src/api/api_client.dart';
import 'src/auth/auth_service.dart';
import 'src/config/app_config.dart';
import 'src/engagement/provider_registry.dart';
import 'src/screens/splash_screen.dart';
import 'src/services/kyc_service.dart';
import 'src/services/notification_service.dart';
import 'src/theme.dart';

void main() {
  runApp(const EarnPlusApp());
}

class EarnPlusApp extends StatefulWidget {
  const EarnPlusApp({super.key});

  @override
  State<EarnPlusApp> createState() => _EarnPlusAppState();
}

class _EarnPlusAppState extends State<EarnPlusApp> {
  late final ApiClient _api;
  late final AppConfigService _configService;
  late final AuthService _authService;
  final AdService _adService = AdService();
  late final InterstitialScheduler _interstitialScheduler;
  final EngagementRegistry _engagement = EngagementRegistry();
  final KycService _kycService = KycService();
  final NotificationService _notificationService = NotificationService();
  bool _adsInitStarted = false;

  @override
  void initState() {
    super.initState();
    _api = ApiClient();
    _configService = AppConfigService(api: _api);
    _authService = AuthService(api: _api);
    _interstitialScheduler = InterstitialScheduler(
      interval: const Duration(minutes: interstitialIntervalMinutes),
      showAd: () => _adService.showUnityInterstitial(),
    );
    _configService.addListener(_maybeInitAds);
    _engagement.initializeAll(); // stub only until a real vendor is plugged in
    _notificationService.ensureWelcome();
    // NOTE: PushService.initialize() is deliberately NOT called here.
  }

  /// Initializes ad networks once /api/config has loaded, using the
  /// backend-supplied ids (falling back to test ids when absent).
  void _maybeInitAds() {
    if (_adsInitStarted) return;
    final config = _configService.config;
    if (config == null) return;
    _adsInitStarted = true;
    _adService.initialize(config);
    // Foreground-time interstitials start only after ads initialize —
    // never on cold start before the first full interval.
    _interstitialScheduler.start();
  }

  @override
  void dispose() {
    _interstitialScheduler.stop();
    _configService.removeListener(_maybeInitAds);
    _configService.dispose();
    _authService.dispose();
    _kycService.dispose();
    _notificationService.dispose();
    _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: _api),
        ChangeNotifierProvider<AppConfigService>.value(value: _configService),
        ChangeNotifierProvider<AuthService>.value(value: _authService),
        ChangeNotifierProvider<KycService>.value(value: _kycService),
        ChangeNotifierProvider<NotificationService>.value(
            value: _notificationService),
        Provider<AdService>.value(value: _adService),
        Provider<InterstitialScheduler>.value(value: _interstitialScheduler),
        Provider<EngagementRegistry>.value(value: _engagement),
      ],
      child: MaterialApp(
        title: 'EarnPlus',
        theme: earnPlusTheme(),
        debugShowCheckedModeBanner: false,
        home: const SplashScreen(),
      ),
    );
  }
}
