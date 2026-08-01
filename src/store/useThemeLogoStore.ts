"use client";

import { create } from "zustand";
import { apiClient } from "@/lib/axios";
import type { ThemeLogoSettings } from "@/lib/theme-logo";

interface ThemeLogoState {
  logo: ThemeLogoSettings;
  isLoading: boolean;
  isFetched: boolean;
  fetchLogoSettings: () => Promise<void>;
}

export const useThemeLogoStore = create<ThemeLogoState>()((set, get) => ({
  logo: {},
  isLoading: false,
  isFetched: false,

  fetchLogoSettings: async () => {
    if (get().isFetched || get().isLoading) return;
    set({ isLoading: true });
    try {
      const res = await apiClient.get<{ logo: ThemeLogoSettings }>("/theme/logo");
      set({ logo: res.data?.logo ?? {}, isLoading: false, isFetched: true });
    } catch {
      set({ isLoading: false, isFetched: true });
    }
  },
}));
