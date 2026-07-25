import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme/app_typography.dart';
import '../theme/child_mode_theme.dart';

/// Centralized text styles.
///
/// These are exposed as getters (not `static final` fields) so that every call
/// site automatically reflects the user's accessibility preferences:
///  * the font **weight** is shifted by the selected weight preference, and
///  * the default **color** adapts to the active (light/dark) brightness.
///
/// Font **size** is intentionally *not* baked in here — it is applied app-wide
/// through `MediaQuery.textScaler`, so these base sizes scale automatically.
///
/// Call sites are unchanged (e.g. `AppTextStyles.bold14`), keeping the refactor
/// non-invasive while making the whole app react to settings changes.
class AppTextStyles {
  AppTextStyles._();

  /// Builds a style at the active weight/color. In Child Mode it uses the
  /// playful face (so these static styles switch fonts app-wide alongside the
  /// theme's `textTheme`); every other theme keeps Cairo. Reads the context-free
  /// [ChildModeTheme.active] flag since these are zero-argument getters.
  static TextStyle _style(
    FontWeight weight,
    double size, {
    double height = 1.0,
  }) {
    final resolved = AppTypography.resolveWeight(weight);
    if (ChildModeTheme.active) {
      return GoogleFonts.getFont(
        AppTypography.playfulFontFamily,
        fontWeight: resolved,
        fontSize: size,
        height: height,
        letterSpacing: 0,
        color: AppTypography.textPrimary,
      );
    }
    return GoogleFonts.cairo(
      fontWeight: resolved,
      fontSize: size,
      height: height,
      letterSpacing: 0,
      color: AppTypography.textPrimary,
    );
  }

  static TextStyle get bold14 => _style(FontWeight.w700, 14);

  static TextStyle get regular14 => _style(FontWeight.w400, 14);

  static TextStyle get medium14 => _style(FontWeight.w500, 14);

  static TextStyle get medium10 => _style(FontWeight.w500, 10);

  static TextStyle get bold16 => _style(FontWeight.w700, 16);

  static TextStyle get regular14Right135 =>
      _style(FontWeight.w400, 14, height: 1.35);

  // ── Semantic type scale ──────────────────────────────────────────────────
  // A clear hierarchy per the design brief. Sizes are base values; the user's
  // font-size preference scales them app-wide via `MediaQuery.textScaler`.
  // Line heights favor comfortable reading — never tighter than needed.

  /// 40 — hero / marketing numerals. Use sparingly.
  static TextStyle get display => _style(FontWeight.w700, 40, height: 1.15);

  /// 32 — page titles.
  static TextStyle get h1 => _style(FontWeight.w700, 32, height: 1.2);

  /// 24 — section headers.
  static TextStyle get h2 => _style(FontWeight.w700, 24, height: 1.25);

  /// 20 — card / group titles.
  static TextStyle get h3 => _style(FontWeight.w600, 20, height: 1.3);

  /// 16 — default body copy.
  static TextStyle get body => _style(FontWeight.w400, 16, height: 1.5);

  /// 16 — emphasized body / inline labels.
  static TextStyle get bodyStrong => _style(FontWeight.w600, 16, height: 1.5);

  /// 14 — secondary text, metadata.
  static TextStyle get caption => _style(FontWeight.w400, 14, height: 1.4);

  /// 12 — fine print, timestamps.
  static TextStyle get small => _style(FontWeight.w400, 12, height: 1.35);
}
