/// Automatic Unity interstitial ads on a foreground-time schedule.
///
/// Owner's rules — read before touching:
/// - Shows every [interval] of app FOREGROUND time only. The timer pauses
///   when the app is backgrounded and resumes on return; it never fires
///   just because the app was reopened.
/// - NEVER interrupts unsafe zones: an in-progress rewarded ad (spin
///   claim), the task/offerwall WebView, or the withdraw
///   confirmation/receipt flow. If the interval elapses inside an unsafe
///   zone, the show is deferred until the zone is released.
/// - Load failure / no fill: skip silently, retry at the next interval.
///   Interstitial failures are never surfaced to the user.
/// - First show happens only after the first full interval elapses —
///   never on cold start.
///
/// The interval comes from [interstitialIntervalMinutes] (lib/config.dart),
/// the single source of truth — nothing here hardcodes minutes.
library;

import 'dart:async';

import 'package:flutter/widgets.dart';

class InterstitialScheduler with WidgetsBindingObserver {
  InterstitialScheduler({
    required this.interval,
    required Future<bool> Function() showAd,
    DateTime Function()? clock,
  })  : _showAd = showAd,
        _clock = clock ?? DateTime.now;

  final Duration interval;

  /// Shows the interstitial. Returns true when an ad was actually shown;
  /// false (no fill / not ready) means "skip silently".
  final Future<bool> Function() _showAd;
  final DateTime Function() _clock;

  Timer? _tick;
  DateTime? _foregroundSince; // null while backgrounded
  Duration _foregroundElapsed = Duration.zero;
  int _unsafeDepth = 0; // > 0 while inside an unsafe zone (nestable)
  bool _deferred = false;
  bool _showing = false;
  bool _started = false;

  bool get isForeground => _foregroundSince != null;

  /// True while inside an unsafe zone (rewarded ad, WebView, withdraw flow).
  bool get isSuppressed => _unsafeDepth > 0;

  void start() {
    if (_started) return;
    _started = true;
    WidgetsBinding.instance.addObserver(this);
    if (WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed &&
        _foregroundSince == null) {
      _foregroundSince = _clock();
    }
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => _onTick());
  }

  void stop() {
    _started = false;
    _tick?.cancel();
    _tick = null;
    _accumulate();
    WidgetsBinding.instance.removeObserver(this);
  }

  /// Enter an unsafe zone — interstitials will not fire inside it.
  /// Nestable: every [suppress] needs a matching [unsuppress].
  void suppress() {
    _unsafeDepth++;
  }

  /// Leave an unsafe zone. A deferred show fires now when safe.
  void unsuppress() {
    if (_unsafeDepth > 0) _unsafeDepth--;
    if (_unsafeDepth == 0 && _deferred && isForeground) {
      _deferred = false;
      _maybeShow();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _foregroundSince ??= _clock();
    } else if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive ||
        state == AppLifecycleState.hidden ||
        state == AppLifecycleState.detached) {
      _accumulate();
    }
  }

  void _accumulate() {
    final since = _foregroundSince;
    if (since != null) {
      _foregroundElapsed += _clock().difference(since);
      _foregroundSince = null;
    }
  }

  void _onTick() {
    if (!isForeground || _showing) return;
    // Close the current window and open a fresh one so background
    // transitions between ticks can't leak time.
    _accumulate();
    _foregroundSince = _clock();
    if (_foregroundElapsed >= interval) {
      if (isSuppressed) {
        _deferred = true;
        return;
      }
      _maybeShow();
    }
  }

  Future<void> _maybeShow() async {
    if (_showing || !isForeground) return;
    if (isSuppressed) {
      _deferred = true;
      return;
    }
    _showing = true;
    try {
      // False = no fill / not ready: silent skip, timer restarts anyway.
      // A throwing showAd is treated the same — never crash the timer.
      try {
        await _showAd();
      } catch (_) {}
    } finally {
      _showing = false;
      _foregroundElapsed = Duration.zero;
      _deferred = false;
    }
  }

  @visibleForTesting
  Duration get debugForegroundElapsed => _foregroundElapsed;

  @visibleForTesting
  bool get debugDeferred => _deferred;
}
