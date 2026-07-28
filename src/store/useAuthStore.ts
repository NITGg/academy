"use client";

import { create } from "zustand";
import { authService } from "@/services/auth.service";
import type { User } from "@/types";
import { getAppUrl } from "@/lib/utils";

interface SessionResponse {
  authenticated: boolean;
  user: User | null;
}

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  setUser: (user: User | null) => void;
  checkSession: () => Promise<void>;
  logout: () => Promise<void>;
}

export const useAuthStore = create<AuthState>()((set) => ({
  user: null,
  isAuthenticated: false,
  isLoading: true,

  setUser: (user) => set({ user, isAuthenticated: !!user, isLoading: false }),

  checkSession: async () => {
    try {
      set({ isLoading: true });
      const { data } = await authService.getSession();
      const sessionData = data as unknown as SessionResponse;

      if (sessionData.authenticated && sessionData.user) {
        set({ user: sessionData.user, isAuthenticated: true, isLoading: false });
      } else {
        set({ user: null, isAuthenticated: false, isLoading: false });
      }
    } catch {
      set({ user: null, isAuthenticated: false, isLoading: false });
    }
  },

  logout: async () => {
    try {
      await authService.logout();
    } finally {
      set({ user: null, isAuthenticated: false, isLoading: false });
      if (typeof window !== "undefined") {
        window.location.assign(getAppUrl("/login"));
      }
    }
  },
}));