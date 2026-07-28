// Auth service — calls /api/auth/* Route Handlers (Phase 1).
// The Route Handlers hold the wstoken in an httpOnly cookie server-side.
// Stub: functions wired up in Phase 1.

import { apiClient } from "@/lib/axios";
import type { LoginInput, RegisterInput } from "@/validations/auth.schema";
import type { AuthSession } from "@/types";

export const authService = {
  login: (data: LoginInput) =>
    apiClient.post<AuthSession>("/auth/login", data),

  loginWithGoogle: (idToken: string) =>
    apiClient.post<AuthSession>("/auth/google", { idToken }),

  register: (data: RegisterInput) =>
    apiClient.post<AuthSession>("/auth/register", data),

  getProfileFields: () =>
    apiClient.get<{ fields: Array<{ id: number; shortname: string; name: string; options?: string[] }> }>("/auth/profile-fields"),

  updateProfile: (data: { firstname?: string; lastname?: string; phone?: string; year?: string; parentPhone?: string }) =>
    apiClient.post<{ user: AuthSession["user"] }>("/auth/profile", data),

  changePassword: (data: { currentPassword: string; newPassword: string }) =>
    apiClient.post<{ success: boolean }>("/auth/change-password", data),

  logout: () => apiClient.post("/auth/logout"),

  getSession: () => apiClient.get<AuthSession>("/auth/session"),
};
