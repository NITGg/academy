"use client";

import { create } from "zustand";
import type { AuthSession, User } from "@/types";

interface AuthState {
  session: AuthSession | null;
  user: User | null;
  isAuthenticated: boolean;
  setSession: (session: AuthSession) => void;
  clearSession: () => void;
}

export const useAuthStore = create<AuthState>()((set) => ({
  session: null,
  user: null,
  isAuthenticated: false,
  setSession: (session) =>
    set({ session, user: session.user, isAuthenticated: true }),
  clearSession: () => set({ session: null, user: null, isAuthenticated: false }),
}));
