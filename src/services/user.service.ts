// User service — stub for Phase 1.
import { apiClient } from "@/lib/axios";
import type { User } from "@/types";

export const userService = {
  getProfile: () => apiClient.get<User>("/user/profile"),
  updateProfile: (data: Partial<User>) =>
    apiClient.patch<User>("/user/profile", data),
};
