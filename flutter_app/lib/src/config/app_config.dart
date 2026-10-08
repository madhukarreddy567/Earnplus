/// Holds the remotely-loaded /api/config for the whole app lifetime.
/// Loaded once on the splash screen; screens read feature flags, branding
/// URLs and ad-network settings from here.
library;

import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../api/models.dart';

class AppConfigService extends ChangeNotifier {
  final ApiClient api;

  AppConfig? _config;
  String? _error;

  AppConfigService({required this.api});

  AppConfig? get config => _config;
  String? get error => _error;
  bool get loaded => _config != null;

  Future<void> load() async {
    try {
      _config = await api.getConfig();
      _error = null;
    } catch (e) {
      _error = e.toString();
    }
    notifyListeners();
  }

  Future<void> reload() => load();
}
