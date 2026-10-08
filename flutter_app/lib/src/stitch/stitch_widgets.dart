/// Reusable widgets in the Stitch design language (new screens only).
library;

import 'package:flutter/material.dart';

import 'stitch_theme.dart';

/// Section heading with optional trailing action.
class StitchSectionHeader extends StatelessWidget {
  final String title;
  final Widget? trailing;
  const StitchSectionHeader({super.key, required this.title, this.trailing});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(title, style: StitchText.title),
        if (trailing != null) trailing!,
      ],
    );
  }
}

/// Small pill showing a status (Verified, Pending, Active, …).
class StitchStatusChip extends StatelessWidget {
  final String label;
  final Color color;
  final Color background;
  const StitchStatusChip(
      {super.key,
      required this.label,
      required this.color,
      required this.background});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
            fontSize: 11, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }
}

/// Row of trust badges (icon + label), e.g. "Secure", "Instant credit".
class StitchTrustBadges extends StatelessWidget {
  final List<(IconData, String)> badges;
  const StitchTrustBadges({super.key, required this.badges});

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: badges
          .map((b) => Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                decoration: BoxDecoration(
                  color: StitchColors.primarySoft,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(b.$1,
                        size: 14, color: StitchColors.primaryDark),
                    const SizedBox(width: 5),
                    Text(b.$2,
                        style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: StitchColors.primaryDark)),
                  ],
                ),
              ))
          .toList(),
    );
  }
}

/// Generic loading / error / empty states in the Stitch language.
class StitchLoading extends StatelessWidget {
  final String message;
  const StitchLoading({super.key, this.message = 'Loading…'});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(color: StitchColors.primary),
          const SizedBox(height: 12),
          Text(message, style: StitchText.caption),
        ],
      ),
    );
  }
}

class StitchError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const StitchError(
      {super.key, required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline,
                size: 48, color: StitchColors.danger),
            const SizedBox(height: 12),
            Text(message,
                textAlign: TextAlign.center, style: StitchText.body),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: onRetry,
              style: OutlinedButton.styleFrom(
                foregroundColor: StitchColors.primaryDark,
                side: const BorderSide(color: StitchColors.primary),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14)),
              ),
              child: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}

class StitchEmpty extends StatelessWidget {
  final IconData icon;
  final String message;
  const StitchEmpty(
      {super.key, required this.icon, required this.message});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: const BoxDecoration(
                color: StitchColors.primarySoft,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 36, color: StitchColors.primaryDark),
            ),
            const SizedBox(height: 14),
            Text(message,
                textAlign: TextAlign.center, style: StitchText.body),
          ],
        ),
      ),
    );
  }
}

/// Live activity ticker: a horizontally scrolling strip of short items.
/// The caller supplies the items (e.g. promotion names); the widget never
/// invents activity on its own.
class StitchTicker extends StatelessWidget {
  final List<String> items;
  const StitchTicker({super.key, required this.items});

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        color: StitchColors.neutral,
        borderRadius: BorderRadius.circular(12),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: Row(
          children: [
            const Icon(Icons.bolt,
                size: 16, color: StitchColors.secondary),
            const SizedBox(width: 8),
            ...items.expand((t) => [
                  Text(t,
                      style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: Colors.white)),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 10),
                    child: Text('•',
                        style: TextStyle(color: StitchColors.secondary)),
                  ),
                ]),
          ],
        ),
      ),
    );
  }
}
