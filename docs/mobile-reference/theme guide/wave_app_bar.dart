import 'package:flutter/material.dart';

import '../theme/child_mode_theme.dart';

/// Wraps a screen's existing [AppBar] and, **only in Child Mode**, gives it a
/// playful wavy bottom edge in the Child accent color.
///
/// Usage is a one-line wrap at each call site:
///
/// ```dart
/// Scaffold(appBar: WaveAppBar(AppBar(title: Text('Settings'))), ...)
/// ```
///
/// In Light/Dark it is a **pure no-op** — [build] returns the child untouched
/// and [preferredSize] is exactly the child's — so existing screens are
/// unchanged. In Child Mode it paints a wave-clipped colored header behind the
/// child bar and forces the bar transparent with white foreground.
///
/// In Child Mode the header grows by [ChildModeTheme.waveHeight] px *below* the
/// bar's own content: the real [AppBar] (title, icons, any `bottom`) sits in the
/// top band untouched, and the wave scallops into the added band beneath it — so
/// nothing overlaps the wave and the curve reads as the bottom edge of the
/// header. `Scaffold` reads [preferredSize] without a [BuildContext], so the
/// extra band is gated on [ChildModeTheme.active] (the app-wide theme mirror)
/// rather than an inherited lookup.
///
/// Note: a handful of screens set an explicit `backgroundColor` on their
/// `AppBar` (e.g. course details, quiz). An explicit widget property beats the
/// [Theme] override below, so those call sites pass
/// `backgroundColor: ChildModeTheme.isActive(context) ? Colors.transparent : <their color>`
/// so the wave shows through in Child Mode while keeping their Light/Dark look.
class WaveAppBar extends StatelessWidget implements PreferredSizeWidget {
  const WaveAppBar(this.child, {super.key});

  /// The screen's real app bar (any [PreferredSizeWidget], normally an [AppBar]).
  final PreferredSizeWidget child;

  /// Whether Child Mode is active for [context]. Re-exported so call sites that
  /// already import this widget don't also need `child_mode_theme.dart`.
  static bool isChild(BuildContext context) => ChildModeTheme.isActive(context);

  /// The app-bar `backgroundColor` to use so the wave shows through: [Colors]
  /// `.transparent` in Child Mode, else [base]. For the few bars that set an
  /// explicit color (which would otherwise cover the wave).
  static Color? barColor(BuildContext context, Color? base) =>
      isChild(context) ? Colors.transparent : base;

  /// Extra height reserved below the bar for the wave band, in Child Mode only.
  /// Read context-free (Scaffold calls this getter without a [BuildContext]).
  double get _waveBand =>
      ChildModeTheme.active ? ChildModeTheme.standard.waveHeight : 0;

  @override
  Size get preferredSize =>
      Size.fromHeight(child.preferredSize.height + _waveBand);

  @override
  Widget build(BuildContext context) {
    final childMode = ChildModeTheme.maybeOf(context);
    if (childMode == null) return child;

    final base = Theme.of(context);
    final band = childMode.waveHeight;
    // Height Scaffold gives the bar = our preferredSize + the status-bar inset.
    // The real bar keeps its natural height (incl. inset); the wave band is the
    // slice below it.
    final barHeight =
        child.preferredSize.height + MediaQuery.paddingOf(context).top;

    return Stack(
      children: [
        // Wavy colored header filling the whole area (bar + band). The scallop
        // is carved into the bottom `band` only, so it sits *below* the content.
        Positioned.fill(
          child: ClipPath(
            clipper: _WaveClipper(band),
            child: ColoredBox(color: childMode.waveColor),
          ),
        ),
        // The real bar pinned to the top, occupying only its own height so the
        // title/icons/bottom never fall into the wave band. Forced transparent +
        // white so the colored fill shows through and stays legible.
        Align(
          alignment: Alignment.topCenter,
          child: SizedBox(
            height: barHeight,
            child: Theme(
              data: base.copyWith(
                appBarTheme: base.appBarTheme.copyWith(
                  backgroundColor: Colors.transparent,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  scrolledUnderElevation: 0,
                ),
              ),
              child: child,
            ),
          ),
        ),
      ],
    );
  }
}

/// Clips a gentle two-hump wave into the bottom [band] of the header, keeping
/// everything above it. The scallops cut *within* the band (never into the bar
/// content above), so the deepest cut stays shallower than [band].
class _WaveClipper extends CustomClipper<Path> {
  const _WaveClipper(this.band);

  /// Height of the wave band at the bottom of the header (px).
  final double band;

  @override
  Path getClip(Size size) {
    final w = size.width;
    final h = size.height;
    // Amplitude kept a comfortable margin inside the band so even the deepest
    // control point (≈ amp * 1.7) never rises above the band into the content.
    final amp = band * 0.5;

    final path =
        Path()
          ..moveTo(0, 0)
          ..lineTo(0, h - amp)
          // First hump (dips down toward the corner, rises to mid).
          ..quadraticBezierTo(w * 0.25, h, w * 0.5, h - amp * 0.55)
          // Second hump (rises a touch higher, settles near the right edge).
          ..quadraticBezierTo(w * 0.75, h - amp * 1.7, w, h - amp * 0.35)
          ..lineTo(w, 0)
          ..close();
    return path;
  }

  @override
  bool shouldReclip(_WaveClipper oldClipper) => oldClipper.band != band;
}
