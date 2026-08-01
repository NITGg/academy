"use client";

import { useEffect } from "react";
import { QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "sonner";
import { getQueryClient } from "@/lib/react-query";
import { useAuthStore } from "@/store/useAuthStore";
import { useThemeStore } from "@/store/useThemeStore";
import { useThemeLogoStore } from "@/store/useThemeLogoStore";

export function Providers({ children }: { children: React.ReactNode }) {
  const queryClient = getQueryClient();
  const checkSession = useAuthStore((state) => state.checkSession);
  const { variant, setTheme } = useThemeStore();
  const fetchLogoSettings = useThemeLogoStore(
    (state) => state.fetchLogoSettings,
  );

  // Check auth session and fetch theme logos on first mount
  useEffect(() => {
    checkSession();
    fetchLogoSettings();
  }, [checkSession, fetchLogoSettings]);

  // Re-apply theme and listen for system preference changes
  useEffect(() => {
    setTheme(variant);

    if (variant !== "system") return;

    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    const handler = () => setTheme("system");
    mq.addEventListener("change", handler);
    return () => mq.removeEventListener("change", handler);
  }, [variant, setTheme]);

  return (
    <QueryClientProvider client={queryClient}>
      {children}
      <Toaster richColors position="top-center" />
    </QueryClientProvider>
  );
}
