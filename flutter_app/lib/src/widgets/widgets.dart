/// Shared UI primitives used across screens.
library;

import 'package:flutter/material.dart';

import '../theme.dart';

/// Hero balance card: coins + rupee equivalent on a green gradient.
class BalanceHero extends StatelessWidget {
  final int coins;
  final double rupees;
  final String? subtitle;

  const BalanceHero({
    super.key,
    required this.coins,
    required this.rupees,
    this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [EarnPlusColors.primary, EarnPlusColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: EarnPlusColors.primary.withValues(alpha: 0.35),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(Icons.monetization_on,
                    color: EarnPlusColors.accent, size: 28),
              ),
              const SizedBox(width: 12),
              Text(
                subtitle ?? 'Total Balance',
                style: const TextStyle(
                  color: Colors.white70,
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            '$coins coins',
            key: const Key('balance_coins'),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 34,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            '≈ ₹${rupees.toStringAsFixed(2)}',
            key: const Key('balance_rupees'),
            style: const TextStyle(
              color: EarnPlusColors.accent,
              fontSize: 20,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

/// One tile of the home quick-action grid.
class QuickActionTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool enabled;

  const QuickActionTile({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
    this.enabled = true,
  });

  @override
  Widget build(BuildContext context) {
    final body = Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: enabled
                ? EarnPlusColors.primaryLight
                : Colors.grey.shade200,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Icon(
            icon,
            color: enabled ? EarnPlusColors.primary : Colors.grey,
            size: 28,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          label,
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: enabled ? EarnPlusColors.ink : Colors.grey,
          ),
        ),
      ],
    );
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(16),
      child: Opacity(opacity: enabled ? 1 : 0.6, child: body),
    );
  }
}

/// Promotion banner with an optional countdown to [endsAt].
class PromoBanner extends StatelessWidget {
  final String title;
  final String subtitle;
  final String? badge;
  final String? endsAt;
  final double multiplier;

  const PromoBanner({
    super.key,
    required this.title,
    required this.subtitle,
    required this.multiplier,
    this.badge,
    this.endsAt,
  });

  String _countdown() {
    if (endsAt == null || endsAt!.isEmpty) return '';
    try {
      final end = DateTime.parse(endsAt!).toLocal();
      final diff = end.difference(DateTime.now());
      if (diff.isNegative) return 'Ended';
      if (diff.inDays > 0) return 'Ends in ${diff.inDays}d ${diff.inHours % 24}h';
      if (diff.inHours > 0) {
        return 'Ends in ${diff.inHours}h ${diff.inMinutes % 60}m';
      }
      return 'Ends in ${diff.inMinutes}m';
    } catch (_) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final countdown = _countdown();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFF7E0), Color(0xFFFFEFC2)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: EarnPlusColors.accent.withValues(alpha: 0.6)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (badge != null && badge!.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: EarnPlusColors.primary,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      badge!,
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w700),
                    ),
                  ),
                if (badge != null && badge!.isNotEmpty)
                  const SizedBox(height: 8),
                Text(title,
                    style: const TextStyle(
                        fontSize: 17, fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text(subtitle,
                    style: const TextStyle(
                        fontSize: 13, color: EarnPlusColors.muted)),
                if (countdown.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(countdown,
                      style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: EarnPlusColors.primary)),
                ],
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: EarnPlusColors.primary,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              '${multiplier}x',
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }
}

/// Full-screen loading spinner.
class LoadingView extends StatelessWidget {
  final String message;
  const LoadingView({super.key, this.message = 'Loading…'});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(color: EarnPlusColors.primary),
          const SizedBox(height: 12),
          Text(message,
              style: const TextStyle(color: EarnPlusColors.muted)),
        ],
      ),
    );
  }
}

/// Full-screen error with a retry button.
class ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const ErrorView({super.key, required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline,
                size: 48, color: EarnPlusColors.danger),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

/// Compact empty-state placeholder.
class EmptyView extends StatelessWidget {
  final IconData icon;
  final String message;
  const EmptyView({super.key, required this.icon, required this.message});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 56, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(message,
                textAlign: TextAlign.center,
                style: const TextStyle(color: EarnPlusColors.muted)),
          ],
        ),
      ),
    );
  }
}

/// Shows a red SnackBar with the backend's error message.
void showApiError(BuildContext context, Object e) {
  final msg = e.toString().replaceFirst('ApiException', 'Error').trim();
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(msg), backgroundColor: EarnPlusColors.danger),
  );
}
