import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'animations/motion.dart';
import 'constants/app_colors.dart';
import 'constants/app_dimens.dart';
import 'theme/app_typography.dart';
import 'theme/child_mode_theme.dart';

/// Builds the app's light and dark [ThemeData].
///
/// Both themes share structure and only differ by [ColorScheme] / surface
/// colors, so adding a third theme later is a matter of another factory here.
/// The text theme's weight is shifted by the user's accessibility preference
/// via [AppTypography]; the font *size* is applied globally through
/// `MediaQuery.textScaler`, not here.
class AppTheme {
  AppTheme._();

  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  /// The colorful, curvy "Child Mode" theme. Light-brightness based, with the
  /// vivid Child palette, enlarged corner radii, and a [ChildModeTheme]
  /// extension so widgets can detect the mode and read its playful tokens.
  static ThemeData child() => _build(Brightness.light, playful: true);

  static ThemeData _build(Brightness brightness, {bool playful = false}) {
    final isDark = brightness == Brightness.dark;
    final primary =
        playful
            ? AppColors.childPrimary
            : (isDark ? AppColors.darkPrimary : AppColors.primary);
    // Page background sits a step below cards so surfaces (white / dark slate)
    // lift off it — the "content on a calm canvas" look from the brief.
    final scaffold =
        playful
            ? AppColors.childBackground
            : (isDark ? AppColors.darkBackground : AppColors.background);
    final surface =
        playful
            ? AppColors.childSurface
            : (isDark ? AppColors.darkSurface : Colors.white);
    final onSurface =
        playful
            ? AppColors.textPrimary
            : (isDark ? AppColors.darkTextPrimary : AppColors.textPrimary);
    final border =
        playful
            ? AppColors.childBorder
            : (isDark ? AppColors.darkBorder : AppColors.border);
    final secondaryText =
        playful
            ? AppColors.textSecondary
            : (isDark ? AppColors.darkTextSecondary : AppColors.textSecondary);
    // Subtle field fill so inputs read as inputs even on white cards.
    final fieldFill =
        playful
            ? AppColors.childBackground
            : (isDark ? AppColors.darkSurfaceVariant : AppColors.surface);

    // Child Mode leans on generous, bubbly rounding; Light/Dark keep the
    // standard token radii (these expressions are identical to the AppRadius
    // getters when not playful, so existing themes are unchanged).
    final cardRadius = BorderRadius.circular(playful ? 28 : AppRadius.card);
    final buttonRadius = BorderRadius.circular(playful ? 22 : AppRadius.button);
    final inputRadius = BorderRadius.circular(playful ? 18 : AppRadius.input);
    final dialogRadius = BorderRadius.circular(playful ? 28 : AppRadius.dialog);
    final sheetRadius = BorderRadius.vertical(
      top: Radius.circular(playful ? 28 : AppRadius.sheet),
    );
    final chipRadius = playful ? 18.0 : AppRadius.chip;

    final colorScheme = ColorScheme.fromSeed(
      seedColor: playful ? AppColors.childPrimary : AppColors.primary,
      brightness: brightness,
    ).copyWith(
      primary: primary,
      onPrimary: Colors.white,
      secondary: playful ? AppColors.childTeal : AppColors.secondary,
      tertiary: playful ? AppColors.childOrange : AppColors.accent,
      surface: surface,
      onSurface: onSurface,
      onSurfaceVariant: secondaryText,
      outline: border,
      outlineVariant: border,
      error: AppColors.error,
    );

    // Child Mode swaps the everyday Cairo face for a rounded, bubbly display
    // font (Baloo Bhaijaan 2 — covers both Latin and Arabic) so the playful
    // theme actually *reads* as playful; Light/Dark keep Cairo unchanged.
    const playfulFontFamily = AppTypography.playfulFontFamily;
    final baseTextTheme =
        playful
            ? GoogleFonts.getTextTheme(
              playfulFontFamily,
              ThemeData.light().textTheme,
            )
            : GoogleFonts.cairoTextTheme(
              isDark ? ThemeData.dark().textTheme : ThemeData.light().textTheme,
            );

    final base = ThemeData(
      brightness: brightness,
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: scaffold,
      textTheme: _applyWeight(
        baseTextTheme,
        onSurface,
        fontFamily: playful ? playfulFontFamily : null,
      ),
    );

    return base.copyWith(
      // Only Child Mode carries the extension; its presence is how widgets
      // detect the mode (Theme.of(context).extension<ChildModeTheme>()).
      extensions:
          playful ? const [ChildModeTheme.standard] : const <ThemeExtension>[],
      // One calm fade-through for every pushed route, in place of the raw
      // per-platform default (see [kAppPageTransitionsTheme]).
      pageTransitionsTheme: kAppPageTransitionsTheme,
      appBarTheme: AppBarTheme(
        backgroundColor: scaffold,
        foregroundColor: onSurface,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
      ),
      // ── Button system ─────────────────────────────────────────────────────
      // Primary (filled), Secondary/Outlined, and Text share one radius and a
      // 48px minimum height for comfortable, accessible touch targets.
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor: border,
          disabledForegroundColor: secondaryText,
          elevation: 0,
          minimumSize: const Size(0, kMinTouchTarget),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.sm,
          ),
          textStyle: const TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 16,
            letterSpacing: 0,
          ),
          shape: RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          minimumSize: const Size(0, kMinTouchTarget),
          shape: RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primary,
          minimumSize: const Size(0, kMinTouchTarget),
          side: BorderSide(color: border),
          textStyle: const TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 16,
            letterSpacing: 0,
          ),
          shape: RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: primary,
          minimumSize: const Size(0, kMinTouchTarget),
          shape: RoundedRectangleBorder(borderRadius: buttonRadius),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: fieldFill,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.md,
        ),
        border: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: primary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: const BorderSide(color: AppColors.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: const BorderSide(color: AppColors.error, width: 1.5),
        ),
        hintStyle: TextStyle(color: secondaryText),
        labelStyle: TextStyle(color: secondaryText),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: fieldFill,
        side: BorderSide(color: border),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(chipRadius),
        ),
      ),
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        backgroundColor: surface,
        selectedItemColor: primary,
        unselectedItemColor: secondaryText,
        elevation: 0,
        type: BottomNavigationBarType.fixed,
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: primary),
      dividerTheme: DividerThemeData(color: border, thickness: 1, space: 1),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: cardRadius,
          side: BorderSide(color: border),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: surface,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: dialogRadius),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: surface,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: sheetRadius),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(chipRadius),
        ),
      ),
    );
  }

  /// Applies the user's font-weight preference and the correct default color to
  /// every entry of a [TextTheme]. [fontFamily] selects which google_fonts face
  /// to (re)bind — Child Mode passes its playful family, Light/Dark leave it
  /// null to keep Cairo.
  static TextTheme _applyWeight(
    TextTheme theme,
    Color color, {
    String? fontFamily,
  }) {
    TextStyle? shift(TextStyle? style) {
      if (style == null) return null;
      final resolved = AppTypography.resolveWeight(
        style.fontWeight ?? FontWeight.w400,
      );
      // Re-create the google_fonts style at the resolved weight rather than a
      // bare `copyWith(fontWeight:)`. google_fonts binds the actual weighted
      // font file at *creation* time, so mutating `fontWeight` after the fact
      // often keeps the original file (or only synthesizes bolding), making the
      // accessibility weight setting barely perceptible on theme-styled text.
      // Re-binding via the same family also preserves Child Mode's playful face.
      return fontFamily == null
          ? GoogleFonts.cairo(
            textStyle: style,
            fontWeight: resolved,
            color: style.color ?? color,
          )
          : GoogleFonts.getFont(
            fontFamily,
            textStyle: style,
            fontWeight: resolved,
            color: style.color ?? color,
          );
    }

    return theme.copyWith(
      displayLarge: shift(theme.displayLarge),
      displayMedium: shift(theme.displayMedium),
      displaySmall: shift(theme.displaySmall),
      headlineLarge: shift(theme.headlineLarge),
      headlineMedium: shift(theme.headlineMedium),
      headlineSmall: shift(theme.headlineSmall),
      titleLarge: shift(theme.titleLarge),
      titleMedium: shift(theme.titleMedium),
      titleSmall: shift(theme.titleSmall),
      bodyLarge: shift(theme.bodyLarge),
      bodyMedium: shift(theme.bodyMedium),
      bodySmall: shift(theme.bodySmall),
      labelLarge: shift(theme.labelLarge),
      labelMedium: shift(theme.labelMedium),
      labelSmall: shift(theme.labelSmall),
    );
  }
}

/// Backwards-compatible entry point (the previous top-level `appTheme()`),
/// now returning the light theme.
@Deprecated('Use AppTheme.light() / AppTheme.dark()')
ThemeData appTheme() => AppTheme.light();
