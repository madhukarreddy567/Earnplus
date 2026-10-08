/// Stitch design system tokens for NEW EarnPlus screens.
///
/// Owner-approved palette from the "EarnPlus Rewards App Screens" Stitch
/// project: green/amber/white, UPI-first Indian market, trust badges and
/// promo cards. Typography: Plus Jakarta Sans (headlines) / Inter (body);
/// the app ships no bundled font files, so these styles use the platform
/// default family with matching weights — the closest available equivalent.
///
/// IMPORTANT: the 11 original screens keep the mPaisa theme in
/// `lib/src/theme.dart`. Nothing here changes them.
library;

import 'package:flutter/material.dart';

class StitchColors {
  static const primary = Color(0xFF10B981); // green
  static const primaryDark = Color(0xFF059669); // tertiary
  static const secondary = Color(0xFFF59E0B); // amber
  static const neutral = Color(0xFF0F172A); // dark slate
  static const background = Color(0xFFF8FAFC); // slate-50
  static const card = Colors.white;
  static const muted = Color(0xFF64748B); // slate-500
  static const line = Color(0xFFE2E8F0); // slate-200
  static const success = Color(0xFF10B981);
  static const danger = Color(0xFFEF4444);
  static const warning = Color(0xFFF59E0B);
  static const primarySoft = Color(0xFFD1FAE5); // green-100
  static const amberSoft = Color(0xFFFEF3C7); // amber-100
}

class StitchText {
  static const headline = TextStyle(
    fontSize: 24,
    fontWeight: FontWeight.w800,
    color: StitchColors.neutral,
    letterSpacing: -0.3,
  );
  static const title = TextStyle(
    fontSize: 17,
    fontWeight: FontWeight.w700,
    color: StitchColors.neutral,
  );
  static const body = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: StitchColors.neutral,
    height: 1.45,
  );
  static const caption = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    color: StitchColors.muted,
  );
  static const button = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w700,
    color: Colors.white,
  );
}

/// AppBar used by Stitch screens: white, dark-slate title, green accents.
AppBar stitchAppBar(String title, {List<Widget>? actions}) => AppBar(
      title: Text(title,
          style: StitchText.title.copyWith(fontWeight: FontWeight.w800)),
      backgroundColor: Colors.white,
      foregroundColor: StitchColors.neutral,
      elevation: 0,
      centerTitle: true,
      actions: actions,
    );

/// Primary CTA button (green) shared by Stitch screens.
Widget stitchPrimaryButton({
  required String label,
  required VoidCallback? onPressed,
  Key? key,
  bool busy = false,
}) =>
    SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        key: key,
        onPressed: busy ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: StitchColors.primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor: StitchColors.line,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          textStyle: StitchText.button,
          elevation: 0,
        ),
        child: busy
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                    strokeWidth: 2, color: Colors.white))
            : Text(label),
      ),
    );

/// Card decoration shared by Stitch screens.
BoxDecoration stitchCardDecoration() => BoxDecoration(
      color: StitchColors.card,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: StitchColors.line),
      boxShadow: [
        BoxShadow(
          color: StitchColors.neutral.withValues(alpha: 0.05),
          blurRadius: 12,
          offset: const Offset(0, 4),
        ),
      ],
    );
