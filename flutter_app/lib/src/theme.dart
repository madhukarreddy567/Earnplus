/// EarnPlus visual system — mPaisa-style mobile-money UI.
/// Deep-green primary, clean white cards, generous rounding.
library;

import 'package:flutter/material.dart';

class EarnPlusColors {
  static const primary = Color(0xFF0B7A3E); // deep green
  static const primaryDark = Color(0xFF075C2F);
  static const primaryLight = Color(0xFFE7F4EC);
  static const accent = Color(0xFFF6C445); // gold
  static const background = Color(0xFFF4F6F5);
  static const card = Colors.white;
  static const ink = Color(0xFF1B1B1B);
  static const muted = Color(0xFF6B7280);
  static const danger = Color(0xFFDC2626);
  static const success = Color(0xFF16A34A);
}

ThemeData earnPlusTheme() {
  final base = ThemeData.light(useMaterial3: true);
  final scheme = ColorScheme.fromSeed(
    seedColor: EarnPlusColors.primary,
    primary: EarnPlusColors.primary,
  );
  return base.copyWith(
    colorScheme: scheme,
    scaffoldBackgroundColor: EarnPlusColors.background,
    appBarTheme: const AppBarTheme(
      backgroundColor: EarnPlusColors.primary,
      foregroundColor: Colors.white,
      elevation: 0,
      centerTitle: true,
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: EarnPlusColors.primary,
        foregroundColor: Colors.white,
        minimumSize: const Size(48, 52),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(48, 52),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
    ),
    cardTheme: CardThemeData(
      color: EarnPlusColors.card,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: const BorderSide(color: Color(0xFFECEFF1)),
      ),
      margin: EdgeInsets.zero,
    ),
  );
}
