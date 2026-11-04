import 'package:flutter/material.dart';

/// ElimuGrid brand palette: Navy (structure/brand), Gold (premium accent),
/// Green (success/financial-positive). Do not introduce ad-hoc colors
/// elsewhere in the app — extend this file instead.
class AppColors {
  AppColors._();

  // Navy — navigation, headers, primary structural elements
  static const Color navy900 = Color(0xFF0A1F44);
  static const Color navy800 = Color(0xFF102A56);
  static const Color navy700 = Color(0xFF17376B);
  static const Color navy600 = Color(0xFF1F4480);
  static const Color navy100 = Color(0xFFE3E9F5);

  // Gold — brand identity, primary CTAs, selected states, premium accents
  static const Color gold700 = Color(0xFFB8860B);
  static const Color gold600 = Color(0xFFD4A017);
  static const Color gold500 = Color(0xFFE8B923);
  static const Color gold100 = Color(0xFFFBF1D6);

  // Green — success, positive financial states, attendance/presence
  static const Color green700 = Color(0xFF1B7A4D);
  static const Color green600 = Color(0xFF209B5F);
  static const Color green100 = Color(0xFFDFF5E8);

  // Semantic (error/warning/info) — kept distinct from brand colors
  static const Color error600 = Color(0xFFD64545);
  static const Color error100 = Color(0xFFFBE4E4);
  static const Color warning600 = Color(0xFFC97A1A);
  static const Color warning100 = Color(0xFFFCEBD6);
  static const Color info600 = Color(0xFF2E7BD6);
  static const Color info100 = Color(0xFFE1EEFB);

  // Neutrals
  static const Color white = Color(0xFFFFFFFF);
  static const Color surface = Color(0xFFF7F8FA);
  static const Color surfaceDark = Color(0xFF0C1526);
  static const Color border = Color(0xFFE2E5EA);
  static const Color borderDark = Color(0xFF223154);
  static const Color textPrimary = Color(0xFF1A1F2B);
  static const Color textSecondary = Color(0xFF5A6478);
  static const Color textOnDark = Color(0xFFF2F4F8);
  static const Color charcoal = Color(0xFF23262E);
}
