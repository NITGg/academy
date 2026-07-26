// Theme utilities shared across components.
// Keep this import-safe for both Server and Client Components — no hooks here.

export type ThemeVariant = "system" | "light" | "dark" | "kids";
export type ResolvedTheme = "light" | "dark" | "kids";

// Kids mode accent colors in order (matches --kids-accent-1..5 in globals.css)
export const KIDS_ACCENTS = [
  "#14B8A6", // teal
  "#FB923C", // orange
  "#EC4899", // pink
  "#FACC15", // yellow
  "#6C5CE7", // violet (primary)
] as const;

/** Pick an accent color by index, wrapping around the 5-color palette. */
export function getKidsAccent(index: number): string {
  return KIDS_ACCENTS[index % KIDS_ACCENTS.length];
}

/** Motion tokens — mirror motion.dart values as plain numbers (ms). */
export const MOTION = {
  fast: 150,
  medium: 250,
  slow: 350,
  easingEmphasized: "cubic-bezier(0.215, 0.610, 0.355, 1.000)",
  easingStandard: "cubic-bezier(0.645, 0.045, 0.355, 1.000)",
} as const;
