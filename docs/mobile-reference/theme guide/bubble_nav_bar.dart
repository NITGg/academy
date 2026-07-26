import 'package:flutter/material.dart';

import '../animations/motion.dart';
import '../constants/app_colors.dart';
import '../theme/child_mode_theme.dart';

/// One tab in the [BubbleNavBar].
class BubbleNavItem {
  const BubbleNavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
  });

  final IconData icon;
  final IconData activeIcon;
  final String label;
}

/// The playful, floating "bubble" navigation bar used **only in Child Mode** in
/// place of the flat [BottomNavigationBar].
///
/// Each tab is a bubble in a rotating accent color (from [ChildModeTheme]); the
/// selected one pops open into a pill that reveals its label, the rest stay as
/// icon-only circles. Wiring mirrors [BottomNavigationBar] ([currentIndex] +
/// [onTap]) so the host doesn't change how it tracks the tab.
class BubbleNavBar extends StatelessWidget {
  const BubbleNavBar({
    super.key,
    required this.items,
    required this.currentIndex,
    required this.onTap,
  });

  final List<BubbleNavItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final childMode = ChildModeTheme.maybeOf(context);
    final accents = childMode?.accents ?? AppColors.childAccents;
    final theme = Theme.of(context);
    final muted = AppColors.textSecondary;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            borderRadius: BorderRadius.circular(28),
            boxShadow: [
              BoxShadow(
                color: AppColors.childPrimary.withValues(alpha: 0.18),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          // Bubbles size to their content (only the selected one expands to a
          // labelled pill); free space is distributed between them. Not wrapped
          // in Flexible so the expanded pill isn't squeezed into a 1/N slot.
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              for (var i = 0; i < items.length; i++)
                _Bubble(
                  item: items[i],
                  selected: i == currentIndex,
                  color: accents[i % accents.length],
                  mutedColor: muted,
                  onTap: () {
                    if (i != currentIndex) selectionHaptic();
                    onTap(i);
                  },
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({
    required this.item,
    required this.selected,
    required this.color,
    required this.mutedColor,
    required this.onTap,
  });

  final BubbleNavItem item;
  final bool selected;
  final Color color;
  final Color mutedColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: AppMotion.medium,
        curve: Curves.easeOutBack,
        padding: EdgeInsets.symmetric(
          horizontal: selected ? 14 : 12,
          vertical: 12,
        ),
        decoration: BoxDecoration(
          color: selected ? color : Colors.transparent,
          borderRadius: BorderRadius.circular(22),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              selected ? item.activeIcon : item.icon,
              color: selected ? Colors.white : mutedColor,
              size: 24,
            ),
            // Label reveals (and hides) with an animated width, so only the
            // selected bubble shows its text.
            AnimatedSize(
              duration: AppMotion.medium,
              curve: AppMotion.emphasized,
              child:
                  selected
                      ? Padding(
                        padding: const EdgeInsetsDirectional.only(start: 6),
                        // Cap the label so an unusually long translation ellipsizes
                        // instead of overflowing the row.
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 88),
                          child: Text(
                            item.label,
                            maxLines: 1,
                            softWrap: false,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      )
                      : const SizedBox.shrink(),
            ),
          ],
        ),
      ),
    );
  }
}
