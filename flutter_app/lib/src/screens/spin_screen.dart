/// Spin wheel.
///
/// The wheel is RENDERED from the server's segment list (GET /api/spin/status)
/// and the spin RESULT comes from the server (POST /api/spin). The app only
/// animates the wheel to the server-returned [SpinResult.segmentIndex] — it
/// never decides the outcome client-side.
///
/// REWARD GATE — READ BEFORE TOUCHING:
/// A win does NOT credit immediately. POST /api/spin returns a single-use
/// [SpinResult.claimToken]; the user must watch a Unity rewarded ad
/// (serverId "{userId}:spin:{claimToken}") and the SERVER credits the wallet
/// only after Unity's signed S2S callback verifies the view. The app polls
/// GET /api/spin/claim/{token} after the ad closes, then calls
/// POST /api/spin/claim. The app NEVER credits coins itself.
library;

import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config.dart';
import '../ads/ad_service.dart';
import '../ads/interstitial_scheduler.dart';
import '../api/api_client.dart';
import '../api/models.dart';
import '../auth/auth_service.dart';
import '../theme.dart';
import '../widgets/widgets.dart';

/// Where the claim card is in the watch-ad-to-claim flow.
enum _ClaimPhase {
  idle,
  awaitingAd, // win landed, waiting for the user to tap "Watch Ad"
  showingAd, // rewarded ad on screen
  verifying, // ad closed; polling the server for S2S verification
  claiming, // calling POST /api/spin/claim
  claimed, // coins credited
  adFailed, // ad failed to load / was not completed
  expired, // claim token expired
}

class SpinScreen extends StatefulWidget {
  const SpinScreen({
    super.key,
    this.claimPollEvery = const Duration(seconds: 2),
    this.claimMaxPolls = 15,
  });

  /// How often to poll GET /api/spin/claim/{token} after the ad closes.
  /// Injectable for tests (production default: every 2s, ~30s max).
  final Duration claimPollEvery;
  final int claimMaxPolls;

  @override
  State<SpinScreen> createState() => _SpinScreenState();
}

class _SpinScreenState extends State<SpinScreen>
    with SingleTickerProviderStateMixin {
  SpinStatus? _status;
  String? _error;
  bool _spinning = false;
  late final AnimationController _controller;
  late Animation<double> _rotation;
  double _accumulated = 0; // total radians rotated so far
  int? _landedIndex;

  // Claim-gate state.
  _ClaimPhase _claimPhase = _ClaimPhase.idle;
  String? _claimToken;
  int _claimAmount = 0;
  DateTime? _claimExpiresAt;
  String? _claimError;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 4),
    );
    _load();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    try {
      final status = await api.spinStatus();
      if (mounted) setState(() => _status = status);
    } on ApiException catch (e) {
      if (e.isNotFound && mounted) {
        setState(() => _error = 'Spins are not enabled right now.');
      } else if (mounted) {
        setState(() => _error = e.message);
      }
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Future<void> _spin() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    final auth = Provider.of<AuthService>(context, listen: false);
    final status = _status;
    if (status == null ||
        _spinning ||
        status.spinsLeft <= 0 ||
        _claimPhase == _ClaimPhase.awaitingAd ||
        _claimPhase == _ClaimPhase.showingAd ||
        _claimPhase == _ClaimPhase.verifying ||
        _claimPhase == _ClaimPhase.claiming) {
      return;
    }

    setState(() {
      _spinning = true;
      _landedIndex = null;
      _error = null;
      _claimPhase = _ClaimPhase.idle;
      _claimToken = null;
      _claimError = null;
    });
    try {
      // The SERVER decides everything. We get the result first, then animate.
      final result = await api.doSpin();
      final segments = result.segments.isNotEmpty
          ? result.segments
          : status.segments;
      final n = segments.length;
      final targetIndex =
          result.segmentIndex.clamp(0, math.max(0, n - 1)).toInt();

      // Animate: 5 full turns + land so the pointer sits on targetIndex.
      // Segment i spans [i*step, (i+1)*step); pointer is at angle 0 (top).
      final step = 2 * math.pi / n;
      final currentMod = _accumulated % (2 * math.pi);
      // We want final rotation R ≡ -(targetIndex*step + step/2) (mod 2π)
      // so the middle of the target segment is under the top pointer.
      final desiredMod =
          (2 * math.pi - (targetIndex * step + step / 2)) % (2 * math.pi);
      var delta = (desiredMod - currentMod) % (2 * math.pi);
      if (delta < 0) delta += 2 * math.pi;
      final total = 5 * 2 * math.pi + delta;

      _rotation = Tween<double>(begin: _accumulated, end: _accumulated + total)
          .animate(CurvedAnimation(
              parent: _controller, curve: Curves.easeOutCubic));
      _controller.reset();
      await _controller.forward();
      _accumulated += total;

      if (!mounted) return;
      setState(() {
        _spinning = false;
        _landedIndex = targetIndex;
        _status = SpinStatus(
          enabled: status.enabled,
          segments: segments,
          spinsLeft: result.spinsLeft,
          dailyLimit: status.dailyLimit,
          adGateEnabled: status.adGateEnabled,
        );
      });
      auth.updateUserBalances(
          coins: result.balance, rupees: auth.user?.rupees ?? 0);

      if (result.won && result.claimToken != null) {
        // Gate: the win is pending until the rewarded ad is verified.
        setState(() {
          _claimPhase = _ClaimPhase.awaitingAd;
          _claimToken = result.claimToken;
          _claimAmount = result.amount;
          _claimExpiresAt = result.claimExpiresAt;
        });
      } else if (result.won) {
        // Gate disabled server-side (or legacy): treat as claimed.
        setState(() => _claimPhase = _ClaimPhase.claimed);
        _showWonSnack(result.amount);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No luck this time — try again!'),
            backgroundColor: EarnPlusColors.muted,
          ),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _spinning = false;
          _error = e.isRateLimited
              ? 'Slow down — you are spinning too fast.'
              : e.message;
        });
        await _load(); // refresh spins_left etc.
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _spinning = false;
          _error = e.toString();
        });
      }
    }
  }

  void _showWonSnack(int amount) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('🎉 ${coinsValueLabel(amount)} claimed!'),
        backgroundColor: EarnPlusColors.success,
      ),
    );
  }

  bool get _claimExpired {
    final exp = _claimExpiresAt;
    return exp != null && DateTime.now().isAfter(exp);
  }

  /// "Watch Ad & Claim" — shows the Unity rewarded ad, then polls the
  /// server until Unity's S2S callback verifies the view (or times out).
  Future<void> _watchAdAndClaim() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    final auth = Provider.of<AuthService>(context, listen: false);
    final ads = Provider.of<AdService>(context, listen: false);
    final scheduler =
        Provider.of<InterstitialScheduler>(context, listen: false);
    final token = _claimToken;
    final userId = auth.user?.id;
    if (token == null || userId == null) return;

    if (_claimExpired) {
      setState(() => _claimPhase = _ClaimPhase.expired);
      return;
    }

    // Never let an interstitial interrupt the rewarded ad.
    scheduler.suppress();
    setState(() {
      _claimPhase = _ClaimPhase.showingAd;
      _claimError = null;
    });

    final adDone = Completer<bool>();
    // Attach the listener BEFORE showing the ad: a synchronous onFailed
    // (no fill) may complete the future before the await below runs.
    final adFuture = adDone.future;
    try {
      await ads.showUnityRewarded(
        userId: userId.toString(),
        // The backend matches Unity's S2S callback to the pending claim
        // through this serverId.
        serverIdOverride: '$userId:spin:$token',
        onFinished: () {
          if (!adDone.isCompleted) adDone.complete(true);
        },
        onFailed: (_) {
          if (!adDone.isCompleted) adDone.complete(false);
        },
      );
      final adOk = await adFuture;
      if (!adOk) {
        throw 'ad failed to show';
      }
    } catch (e) {
      if (!mounted) {
        scheduler.unsuppress();
        return;
      }
      // No fill / load failure: silent-ish retry path — the claim stays
      // valid until expiry, the user can tap Watch Ad again.
      setState(() {
        _claimPhase = _ClaimPhase.adFailed;
        _claimError = 'The ad could not be loaded. Check your connection and try again — your reward is safe.';
      });
      scheduler.unsuppress();
      return;
    }

    if (!mounted) {
      scheduler.unsuppress();
      return;
    }
    setState(() => _claimPhase = _ClaimPhase.verifying);

    // Unity's S2S callback arrives asynchronously — poll the claim status
    // until the server sees it (or we time out). The SERVER decides;
    // the ad-close callback alone never credits.
    final pollEvery = widget.claimPollEvery;
    final maxPolls = widget.claimMaxPolls;
    var verified = false;
    for (var i = 0; i < maxPolls; i++) {
      await Future<void>.delayed(pollEvery);
      if (!mounted) {
        scheduler.unsuppress();
        return;
      }
      try {
        final st = await api.spinClaimStatus(token);
        if (st.claimed) {
          // Already credited (e.g. double tap) — refresh and finish.
          final wallet = await api.getWallet();
          auth.updateUserBalances(
              coins: wallet.coins, rupees: auth.user?.rupees ?? 0);
          setState(() => _claimPhase = _ClaimPhase.claimed);
          scheduler.unsuppress();
          return;
        }
        if (st.adVerified) {
          verified = true;
          break;
        }
        if (st.expired) {
          setState(() => _claimPhase = _ClaimPhase.expired);
          scheduler.unsuppress();
          return;
        }
      } on ApiException {
        // Transient — keep polling.
      }
    }

    if (!verified) {
      // Ad was closed but Unity never confirmed (skipped, or S2S delayed).
      // The claim stays valid until expiry — the user can retry.
      if (mounted) {
        setState(() {
          _claimPhase = _ClaimPhase.adFailed;
          _claimError =
              'Ad not completed — reward not claimed. Tap below to try again.';
        });
      }
      scheduler.unsuppress();
      return;
    }

    if (!mounted) {
      scheduler.unsuppress();
      return;
    }
    setState(() => _claimPhase = _ClaimPhase.claiming);
    try {
      final result = await api.claimSpin(token);
      if (!mounted) {
        scheduler.unsuppress();
        return;
      }
      auth.updateUserBalances(
          coins: result.balance, rupees: auth.user?.rupees ?? 0);
      setState(() => _claimPhase = _ClaimPhase.claimed);
      _showWonSnack(result.coins);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _claimPhase = _ClaimPhase.adFailed;
          _claimError = e.message;
        });
      }
    } finally {
      scheduler.unsuppress();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Spin & Win')),
      body: _error != null && _status == null
          ? ErrorView(message: _error!, onRetry: () {
              setState(() => _error = null);
              _load();
            })
          : _status == null
              ? const LoadingView(message: 'Loading wheel…')
              : _buildBody(),
    );
  }

  Widget _buildBody() {
    final status = _status!;
    final segments = status.segments;
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          Text(
            '${status.spinsLeft} / ${status.dailyLimit} spins left today',
            key: const Key('spins_left'),
            style: const TextStyle(
                fontSize: 16, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          Expanded(
            child: Center(
              child: AspectRatio(
                aspectRatio: 1,
                child: Stack(
                  alignment: Alignment.topCenter,
                  children: [
                    AnimatedBuilder(
                      animation: _controller,
                      builder: (_, __) => Transform.rotate(
                        angle: _spinning ? _rotation.value : _accumulated,
                        child: CustomPaint(
                          painter: _WheelPainter(segments: segments),
                          child: const SizedBox.expand(),
                        ),
                      ),
                    ),
                    // Pointer
                    Container(
                      margin: const EdgeInsets.only(top: 2),
                      width: 0,
                      height: 0,
                      decoration: const BoxDecoration(
                        border: Border(
                          top: BorderSide(
                              width: 26, color: EarnPlusColors.danger),
                          left: BorderSide(
                              width: 14, color: Colors.transparent),
                          right: BorderSide(
                              width: 14, color: Colors.transparent),
                        ),
                      ),
                    ),
                    // Hub
                    Center(
                      child: Container(
                        width: 64,
                        height: 64,
                        decoration: const BoxDecoration(
                          color: EarnPlusColors.primary,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.casino,
                            color: Colors.white, size: 32),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 16),
          if (_landedIndex != null && segments.isNotEmpty)
            Text(
              'Landed on +${segments[_landedIndex!]} coins',
              style: const TextStyle(
                  fontSize: 15, fontWeight: FontWeight.w600),
            ),
          const SizedBox(height: 12),
          _buildActionArea(status),
        ],
      ),
    );
  }

  Widget _buildActionArea(SpinStatus status) {
    switch (_claimPhase) {
      case _ClaimPhase.awaitingAd:
      case _ClaimPhase.adFailed:
        return _buildClaimCard();
      case _ClaimPhase.showingAd:
        return const _ClaimBusyCard(
            message: 'Showing your reward video…');
      case _ClaimPhase.verifying:
        return const _ClaimBusyCard(
            message: 'Confirming your ad view…');
      case _ClaimPhase.claiming:
        return const _ClaimBusyCard(
            message: 'Claiming your coins…');
      case _ClaimPhase.claimed:
        return _buildSpinButton(status, justClaimed: true);
      case _ClaimPhase.expired:
        return Column(
          children: [
            const Text(
              'This reward expired — spin again!',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 12),
            _buildSpinButton(status),
          ],
        );
      case _ClaimPhase.idle:
        return _buildSpinButton(status);
    }
  }

  Widget _buildSpinButton(SpinStatus status, {bool justClaimed = false}) {
    final canSpin = !_spinning &&
        status.spinsLeft > 0 &&
        _claimPhase != _ClaimPhase.showingAd &&
        _claimPhase != _ClaimPhase.verifying &&
        _claimPhase != _ClaimPhase.claiming;
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        key: const Key('spin_button'),
        onPressed: canSpin ? _spin : null,
        child: _spinning
            ? const Text('Spinning…')
            : Text(status.spinsLeft <= 0
                ? 'No spins left today'
                : justClaimed
                    ? 'SPIN AGAIN'
                    : 'SPIN'),
      ),
    );
  }

  Widget _buildClaimCard() {
    return Container(
      key: const Key('claim_card'),
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: EarnPlusColors.success.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
            color: EarnPlusColors.success.withValues(alpha: 0.35)),
      ),
      child: Column(
        children: [
          Text(
            '🎉 You won ${coinsValueLabel(_claimAmount)}!',
            style: const TextStyle(
                fontSize: 17, fontWeight: FontWeight.w800),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 6),
          const Text(
            'Watch a short video to claim your coins.',
            style: TextStyle(fontSize: 14),
            textAlign: TextAlign.center,
          ),
          if (_claimError != null) ...[
            const SizedBox(height: 8),
            Text(
              _claimError!,
              key: const Key('claim_error'),
              style: const TextStyle(
                  fontSize: 13, color: EarnPlusColors.danger),
              textAlign: TextAlign.center,
            ),
          ],
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              key: const Key('watch_ad_button'),
              onPressed: _watchAdAndClaim,
              child: const Text('📺 WATCH AD & CLAIM'),
            ),
          ),
        ],
      ),
    );
  }
}

class _ClaimBusyCard extends StatelessWidget {
  final String message;
  const _ClaimBusyCard({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: EarnPlusColors.primary.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2.5),
          ),
          const SizedBox(width: 12),
          Flexible(child: Text(message)),
        ],
      ),
    );
  }
}

class _WheelPainter extends CustomPainter {
  final List<int> segments;
  const _WheelPainter({required this.segments});

  @override
  void paint(Canvas canvas, Size size) {
    final n = segments.isEmpty ? 8 : segments.length;
    final radius = size.width / 2;
    final center = Offset(radius, radius);
    final step = 2 * math.pi / n;

    const palette = [
      Color(0xFF0B7A3E),
      Color(0xFFF6C445),
      Color(0xFF149A52),
      Color(0xFFFFE08A),
      Color(0xFF075C2F),
      Color(0xFFFBD38D),
    ];

    for (var i = 0; i < n; i++) {
      final paint = Paint()
        ..color = palette[i % palette.length]
        ..style = PaintingStyle.fill;
      // Start at -π/2 so segment 0 is centered at the top pointer when
      // rotation is 0 — matches the landing math in _spin().
      final start = -math.pi / 2 + i * step;
      canvas.drawArc(
          Rect.fromCircle(center: center, radius: radius),
          start,
          step,
          true,
          paint);
      // Label
      final label = segments.isEmpty ? '?' : '${segments[i]}';
      final tp = TextPainter(
        text: TextSpan(
          text: label,
          style: const TextStyle(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w800),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      final angle = start + step / 2;
      final labelRadius = radius * 0.68;
      final pos = Offset(
        center.dx + labelRadius * math.cos(angle) - tp.width / 2,
        center.dy + labelRadius * math.sin(angle) - tp.height / 2,
      );
      tp.paint(canvas, pos);
      // Divider line
      canvas.drawLine(
          center,
          center + Offset(radius * math.cos(start), radius * math.sin(start)),
          Paint()
            ..color = Colors.white.withValues(alpha: 0.6)
            ..strokeWidth = 2);
    }
    // Rim
    canvas.drawCircle(
        center,
        radius,
        Paint()
          ..color = EarnPlusColors.primaryDark
          ..style = PaintingStyle.stroke
          ..strokeWidth = 6);
  }

  @override
  bool shouldRepaint(covariant _WheelPainter old) =>
      old.segments != segments;
}
