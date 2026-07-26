import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// One place for the app's motion vocabulary — durations, curves and the shared
/// page transition — so every animated surface reads as part of the same system
/// instead of each screen inventing its own timing.
class AppMotion {
  AppMotion._();

  /// Quick state flips (chip select, icon swaps, press feedback).
  static const Duration fast = Duration(milliseconds: 150);

  /// The default for most UI transitions (cross-fades, entrances, expand).
  static const Duration medium = Duration(milliseconds: 250);

  /// Slower, more deliberate motion (page transitions, hero flights).
  static const Duration slow = Duration(milliseconds: 350);

  /// Standard easing for entrances and emphasis.
  static const Curve emphasized = Curves.easeOutCubic;

  /// Symmetric easing for cross-fades and reversible transitions.
  static const Curve standard = Curves.easeInOutCubic;
}

/// A calm fade-through page transition: the incoming page fades in while easing
/// up a few pixels, and the outgoing page fades away underneath it. Applied
/// globally via [PageTransitionsTheme] so every pushed route shares it without
/// touching the 20-odd `Navigator.push` call sites.
class FadeThroughPageTransitionsBuilder extends PageTransitionsBuilder {
  const FadeThroughPageTransitionsBuilder();

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    final primary = CurvedAnimation(
      parent: animation,
      curve: AppMotion.standard,
      reverseCurve: AppMotion.standard.flipped,
    );
    final secondary = CurvedAnimation(
      parent: secondaryAnimation,
      curve: AppMotion.standard,
    );
    return FadeTransition(
      opacity: primary,
      child: FadeTransition(
        opacity: Tween<double>(begin: 1, end: 0).animate(secondary),
        child: SlideTransition(
          position: Tween<Offset>(
            begin: const Offset(0, 0.02),
            end: Offset.zero,
          ).animate(primary),
          child: child,
        ),
      ),
    );
  }
}

/// The [PageTransitionsTheme] plugged into both light and dark [ThemeData], so
/// Android, iOS and every other platform share the app's fade-through motion.
const PageTransitionsTheme kAppPageTransitionsTheme = PageTransitionsTheme(
  builders: {
    TargetPlatform.android: FadeThroughPageTransitionsBuilder(),
    TargetPlatform.iOS: FadeThroughPageTransitionsBuilder(),
    TargetPlatform.macOS: FadeThroughPageTransitionsBuilder(),
    TargetPlatform.windows: FadeThroughPageTransitionsBuilder(),
    TargetPlatform.linux: FadeThroughPageTransitionsBuilder(),
    TargetPlatform.fuchsia: FadeThroughPageTransitionsBuilder(),
  },
);

/// Light haptic feedback for a committed action (booking sent, purchase
/// confirmed). Wrapped so call sites read intent, and so it can be muted in one
/// place later if we add a "reduce haptics" preference.
Future<void> commitHaptic() => HapticFeedback.mediumImpact();

/// A subtle tick for selection changes (chips, tabs). Cheaper than
/// [commitHaptic]; use it for reversible state, not commitments.
Future<void> selectionHaptic() => HapticFeedback.selectionClick();
