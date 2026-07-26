import 'package:flutter/material.dart';

import '../constants/app_colors.dart';
import '../settings/app_settings_enums.dart';

/// Global, lightweight holder for the *currently active* typography config.
///
/// It exists so the app's static text styles ([AppTextStyles]) can stay
/// zero-argument getters — matching every existing call site — while still
/// reacting to the user's font-weight preference and the active brightness.
///
/// The values here are kept in sync from two places:
///  * [SettingsCubit] updates the font-weight delta whenever settings change.
///  * `MaterialApp.builder` updates [brightness] to the resolved theme
///    brightness on every rebuild.
///
/// Because the whole widget tree rebuilds when either changes, the getters are
/// re-evaluated with fresh values — no manual per-screen wiring required.
class AppTypography {
  AppTypography._();

  /// The rounded, playful display face used only in Child Mode (covers Latin +
  /// Arabic). Single source of truth for both the theme's `textTheme`
  /// (`AppTheme._build`) and the static [AppTextStyles] getters, so *all* text
  /// switches faces together. Gated on [ChildModeTheme.active].
  static const String playfulFontFamily = 'Baloo Bhaijaan 2';

  static int _weightDelta = 0;
  static Brightness brightness = Brightness.light;

  /// Sync the parts derived from settings state (currently the weight delta).
  static void sync(AppFontWeight fontWeight) {
    _weightDelta = fontWeight.weightDelta;
  }

  static bool get isDark => brightness == Brightness.dark;

  /// Shifts a base [FontWeight] by the user's weight preference, clamped to the
  /// valid range, preserving the relative hierarchy of the type scale.
  static FontWeight resolveWeight(FontWeight base) {
    if (_weightDelta == 0) return base;
    final index = (FontWeight.values.indexOf(base) + _weightDelta).clamp(
      0,
      FontWeight.values.length - 1,
    );
    return FontWeight.values[index];
  }

  /// Primary text color for the active brightness.
  static Color get textPrimary =>
      isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;

  /// Secondary/muted text color for the active brightness.
  static Color get textSecondary =>
      isDark ? AppColors.darkTextSecondary : AppColors.textSecondary;
}
