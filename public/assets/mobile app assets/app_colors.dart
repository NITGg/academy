import 'package:flutter/material.dart';

/// The app's semantic color palette — a calm, professional educational system.
///
/// Values follow the design brief (Tailwind-style blue/slate scale):
///  * Primary   #2563EB  (blue-600)   — brand, primary actions
///  * Secondary #10B981  (emerald-500) — positive / secondary emphasis
///  * Accent    #F59E0B  (amber-500)  — highlights, badges
///  * Neutrals  slate scale for text, borders, backgrounds
///
/// Prefer `Theme.of(context).colorScheme` for new work; these constants back the
/// theme and the few widgets that need a direct color outside of it.
class AppColors {
  // ── Brand ───────────────────────────────────────────────────────────────
  static const Color primary = Color(0xFF2563EB);
  static const Color primaryLight = Color(0xFFDBEAFE); // blue-100 tint
  static const Color secondary = Color(0xFF10B981); // emerald-500
  static const Color accent = Color(0xFFF59E0B); // amber-500

  // ── Neutrals ────────────────────────────────────────────────────────────
  /// Deep slate — dark surfaces (splash) and high-emphasis dark buttons.
  static const Color dark = Color(0xFF0F172A); // slate-900
  static const Color background = Color(0xFFF8FAFC); // slate-50, page bg
  static const Color surface = Color(0xFFF1F5F9); // slate-100, subtle fills
  static const Color textPrimary = Color(0xFF0F172A); // slate-900
  static const Color textSecondary = Color(0xFF64748B); // slate-500
  static const Color border = Color(0xFFE2E8F0); // slate-200

  // ── Semantic ────────────────────────────────────────────────────────────
  static const Color error = Color(0xFFEF4444); // red-500
  static const Color success = Color(0xFF10B981); // emerald-500
  static const Color warning = Color(0xFFF59E0B); // amber-500

  // Deprecated — use the names above instead.
  static const Color blue = primaryLight;
  static const Color textGrey = textSecondary;
  static const Color borderGrey = border;

  // ── Dark palette ────────────────────────────────────────────────────────
  // Slate-tinted to sit harmoniously under the blue brand. Used by the dark
  // [ThemeData] and by the brightness-aware helpers below.
  static const Color darkPrimary = Color(0xFF60A5FA); // blue-400
  static const Color darkBackground = Color(0xFF0F172A); // slate-900
  static const Color darkSurface = Color(0xFF1E293B); // slate-800
  static const Color darkSurfaceVariant = Color(0xFF334155); // slate-700
  static const Color darkTextPrimary = Color(0xFFF1F5F9); // slate-100
  static const Color darkTextSecondary = Color(0xFF94A3B8); // slate-400
  static const Color darkBorder = Color(0xFF334155); // slate-700

  // ── Child Mode palette ──────────────────────────────────────────────────
  // A bright, playful set for the colorful "Child Mode" theme. Light-brightness
  // based (see [AppThemeOption.child]); the vivid primary + warm background +
  // multi-color accents give the app its kid-friendly feel. Used by
  // `AppTheme.child()` and carried into widgets via `ChildModeTheme`.
  static const Color childPrimary = Color(0xFF6C5CE7); // vivid violet
  static const Color childBackground = Color(0xFFFFF7F2); // warm off-white
  static const Color childSurface = Colors.white;
  static const Color childBorder = Color(0xFFF0E6FF); // soft violet tint

  /// Playful accent set — rotate through these for bubbles, nodes and badges.
  static const Color childTeal = Color(0xFF14B8A6);
  static const Color childOrange = Color(0xFFFB923C);
  static const Color childPink = Color(0xFFEC4899);
  static const Color childYellow = Color(0xFFFACC15);
  static const List<Color> childAccents = [
    childTeal,
    childOrange,
    childPink,
    childYellow,
    childPrimary,
  ];

  /// Returns the brightness-appropriate variant of a semantic color. Prefer
  /// `Theme.of(context).colorScheme` where possible; this helper is for the
  /// few widgets that need a direct color outside the theme.
  static Color surfaceOf(Brightness b) =>
      b == Brightness.dark ? darkSurface : surface;

  /// A subtle raised fill for chips/hint boxes/tiles that sit *on top of* a
  /// card or sheet. Steps up one level in dark mode so it stays visible against
  /// [darkSurface]; in light mode it's the gentle slate fill.
  static Color fillOf(Brightness b) =>
      b == Brightness.dark ? darkSurfaceVariant : surface;

  static Color cardOf(Brightness b) =>
      b == Brightness.dark ? darkSurface : Colors.white;

  static Color backgroundOf(Brightness b) =>
      b == Brightness.dark ? darkBackground : background;

  static Color textPrimaryOf(Brightness b) =>
      b == Brightness.dark ? darkTextPrimary : textPrimary;

  static Color textSecondaryOf(Brightness b) =>
      b == Brightness.dark ? darkTextSecondary : textSecondary;

  static Color borderOf(Brightness b) =>
      b == Brightness.dark ? darkBorder : border;

  static Color primaryOf(Brightness b) =>
      b == Brightness.dark ? darkPrimary : primary;
}
