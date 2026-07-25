import 'package:flutter/material.dart';

/// Design tokens for spacing, corner radii and elevation.
///
/// These give the whole app one consistent rhythm. Reach for these instead of
/// hardcoding magic numbers so layouts stay aligned to the 8-point grid and
/// components share the same corner + shadow language.
///
/// Prefer the named steps; if you need a one-off, round to the nearest token.
class AppSpacing {
  AppSpacing._();

  /// 4 — hairline gaps, icon/label separation.
  static const double xxs = 4;

  /// 8 — tight padding, chip insets.
  static const double xs = 8;

  /// 12 — compact list rows.
  static const double sm = 12;

  /// 16 — default padding / gutter.
  static const double md = 16;

  /// 24 — section padding, card insets.
  static const double lg = 24;

  /// 32 — between major sections.
  static const double xl = 32;

  /// 40 — generous vertical rhythm.
  static const double xxl = 40;

  /// 48 — hero spacing.
  static const double xxxl = 48;

  /// 64 — top-level screen breathing room.
  static const double huge = 64;
}

/// Corner radii. One value per component family so rounding stays consistent.
class AppRadius {
  AppRadius._();

  static const double card = 20;
  static const double button = 16;
  static const double input = 14;
  static const double dialog = 24;
  static const double sheet = 24;

  /// Small chips / tags.
  static const double chip = 12;

  /// Fully rounded (pills, avatars).
  static const double pill = 999;

  static BorderRadius get cardRadius => BorderRadius.circular(card);
  static BorderRadius get buttonRadius => BorderRadius.circular(button);
  static BorderRadius get inputRadius => BorderRadius.circular(input);
  static BorderRadius get dialogRadius => BorderRadius.circular(dialog);
  static BorderRadius get sheetRadius =>
      const BorderRadius.vertical(top: Radius.circular(sheet));
}

/// Soft, subtle elevation — no heavy Material drop shadows.
///
/// Shadows are brightness-aware: on dark surfaces we lean on borders and near
/// invisible shadows so cards read through separation rather than darkness.
class AppShadows {
  AppShadows._();

  /// Resting card elevation.
  static List<BoxShadow> card(Brightness b) {
    if (b == Brightness.dark) return const [];
    return const [
      BoxShadow(
        color: Color(0x0F0F172A), // slate-900 @ ~6%
        blurRadius: 12,
        offset: Offset(0, 4),
      ),
    ];
  }

  /// Raised elements — sheets, popovers, floating actions.
  static List<BoxShadow> raised(Brightness b) {
    if (b == Brightness.dark) {
      return const [
        BoxShadow(
          color: Color(0x40000000),
          blurRadius: 16,
          offset: Offset(0, 6),
        ),
      ];
    }
    return const [
      BoxShadow(
        color: Color(0x1A0F172A), // slate-900 @ ~10%
        blurRadius: 24,
        offset: Offset(0, 8),
      ),
    ];
  }
}

/// Minimum recommended tappable area (accessibility).
const double kMinTouchTarget = 48;

/// The combined text-scale factor currently in effect (1.0 = default).
///
/// Multiply fixed heights of text-bearing widgets (carousel rows, cards,
/// chip strips) by this so enlarged accessibility fonts get room to breathe
/// instead of overflowing.
double textScaleFactorOf(BuildContext context) =>
    MediaQuery.textScalerOf(context).scale(100) / 100;
