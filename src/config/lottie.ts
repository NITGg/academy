// Kids mode Lottie animation slot registry.
// Mirrors the mobile app's kPlayfulAnimationPaths pattern:
//   - Every slot defaults to null (no file = renders nothing, never crashes)
//   - Add a new animation by setting its path here — zero component changes needed
//   - All slots are Kids-mode-only; in Light/Dark these are never rendered

export type LottieSlot =
  | "quizSuccess"
  | "celebration"
  | "childModeOn"
  | "loading"
  | "courseDetails"
  | "quizScreen"
  | "emptyState"    // pending — asset not yet designed
  | "homeGreeting"; // pending — asset not yet designed

export const KIDS_LOTTIE_SLOTS: Record<LottieSlot, string | null> = {
  quizSuccess:    "/assets/lottie/bird-confity.json",
  celebration:    "/assets/lottie/Celebration.json",
  childModeOn:    "/assets/lottie/child_mode_on.json",
  loading:        "/assets/lottie/loading.json",
  courseDetails:  "/assets/lottie/Reading.json",
  quizScreen:     "/assets/lottie/solving.json",
  emptyState:     null, // asset pending from design team
  homeGreeting:   null, // asset pending from design team
};

// Returns the rotating Kids accent color CSS variable for a given index.
// Usage: style={{ color: `var(${getKidsAccentVar(index)})` }}
export function getKidsAccentVar(index: number): string {
  return `--kids-accent-${(index % 5) + 1}`;
}
