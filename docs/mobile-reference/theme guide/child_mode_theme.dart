import 'package:flutter/material.dart';

import '../constants/app_colors.dart';

/// Theme marker + playful tokens for the colorful "Child Mode".
///
/// Attached to the [ThemeData] built by `AppTheme.child()` and to *nothing*
/// else, so any widget can cheaply ask "am I in Child Mode?" without reaching
/// for `SettingsCubit`:
///
/// ```dart
/// final child = Theme.of(context).extension<ChildModeTheme>();
/// if (child != null) { /* render the playful variant */ }
/// ```
///
/// It also carries the few extra tokens the playful widgets need (wave color +
/// height, the rotating accent set) so they stay in one place.
@immutable
class ChildModeTheme extends ThemeExtension<ChildModeTheme> {
  const ChildModeTheme({
    required this.accents,
    required this.waveColor,
    required this.waveHeight,
  });

  /// Rotating accent colors for bubbles, map nodes and badges.
  final List<Color> accents;

  /// Fill color of the wavy app-bar header.
  final Color waveColor;

  /// Extra height (px) the wave adds below the standard app bar.
  final double waveHeight;

  /// The standard Child Mode tokens.
  static const ChildModeTheme standard = ChildModeTheme(
    accents: AppColors.childAccents,
    waveColor: AppColors.childPrimary,
    // Extra height (px) the wavy header adds *below* the app-bar content, so the
    // title/icons stay fully visible and the wave scallops beneath them.
    waveHeight: 30,
  );

  /// App-wide mirror of "is Child Mode the active theme", kept in sync by
  /// `main.dart` from the resolved [ThemeData]. Exists purely so widgets that
  /// must answer that question *without* a [BuildContext] — notably
  /// `WaveAppBar.preferredSize`, which `Scaffold` reads context-free — can do
  /// so. Prefer [isActive]/[maybeOf] anywhere a context is available.
  static bool active = false;

  /// Picks an accent from [accents] by [index], wrapping around — handy for
  /// coloring a list of bubbles/nodes so neighbors differ.
  Color accentAt(int index) => accents[index % accents.length];

  @override
  ChildModeTheme copyWith({
    List<Color>? accents,
    Color? waveColor,
    double? waveHeight,
  }) {
    return ChildModeTheme(
      accents: accents ?? this.accents,
      waveColor: waveColor ?? this.waveColor,
      waveHeight: waveHeight ?? this.waveHeight,
    );
  }

  /// The [ChildModeTheme] in effect, or `null` when not in Child Mode.
  static ChildModeTheme? maybeOf(BuildContext context) =>
      Theme.of(context).extension<ChildModeTheme>();

  /// Whether Child Mode is the active theme for [context].
  static bool isActive(BuildContext context) => maybeOf(context) != null;

  @override
  ChildModeTheme lerp(ThemeExtension<ChildModeTheme>? other, double t) {
    if (other is! ChildModeTheme) return this;
    return ChildModeTheme(
      // Accent list length is fixed, so lerp element-wise.
      accents: [
        for (var i = 0; i < accents.length; i++)
          Color.lerp(accents[i], other.accents[i], t) ?? accents[i],
      ],
      waveColor: Color.lerp(waveColor, other.waveColor, t) ?? waveColor,
      waveHeight: (waveHeight + (other.waveHeight - waveHeight) * t),
    );
  }
}
