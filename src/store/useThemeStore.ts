"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";

// Four user-facing options; "system" resolves to light/dark at runtime.
export type ThemeVariant = "system" | "light" | "dark" | "kids";
export type ResolvedTheme = "light" | "dark" | "kids";

interface ThemeState {
  variant: ThemeVariant;
  resolved: ResolvedTheme;
  setTheme: (variant: ThemeVariant) => void;
}

function resolveVariant(variant: ThemeVariant): ResolvedTheme {
  if (variant === "system") {
    if (typeof window === "undefined") return "light";
    return window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }
  return variant;
}

function applyTheme(resolved: ResolvedTheme) {
  if (typeof document === "undefined") return;
  const root = document.documentElement;

  // data-theme drives all CSS custom-property overrides (including kids palette)
  root.setAttribute("data-theme", resolved);

  // .dark class keeps shadcn / Tailwind @custom-variant dark working
  root.classList.toggle("dark", resolved === "dark");
}

export const useThemeStore = create<ThemeState>()(
  persist(
    (set, get) => ({
      variant: "light",
      resolved: "light",

      setTheme: (variant) => {
        const resolved = resolveVariant(variant);
        set({ variant, resolved });
        applyTheme(resolved);
      },
    }),
    {
      name: "ea-theme",
      onRehydrateStorage: () => (state) => {
        if (state) {
          // Re-apply after hydration from localStorage
          const resolved = resolveVariant(state.variant);
          state.resolved = resolved;
          applyTheme(resolved);
        }
      },
    }
  )
);
