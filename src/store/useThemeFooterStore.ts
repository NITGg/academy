"use client";

import { create } from "zustand";
import { apiClient } from "@/lib/axios";
import type { ThemeFooterSettings } from "@/lib/theme-footer";

interface ThemeFooterState {
  footer: ThemeFooterSettings;
  isLoading: boolean;
  /** Language the current `footer` was fetched for — refetch when the locale changes. */
  fetchedLang: string | null;
  fetchFooterSettings: (lang: string) => Promise<void>;
}

export const useThemeFooterStore = create<ThemeFooterState>()((set, get) => ({
  footer: {},
  isLoading: false,
  fetchedLang: null,

  fetchFooterSettings: async (lang: string) => {
    if (get().isLoading) return;
    if (get().fetchedLang === lang) return; // already have this language
    set({ isLoading: true });
    try {
      const res = await apiClient.get<{ footer: ThemeFooterSettings }>(
        `/theme/footer?lang=${encodeURIComponent(lang)}`,
      );
      set({ footer: res.data?.footer ?? {}, isLoading: false, fetchedLang: lang });
    } catch {
      set({ isLoading: false, fetchedLang: lang });
    }
  },
}));
