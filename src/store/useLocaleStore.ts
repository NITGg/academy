"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";
import { LOCALE_COOKIE } from "@/config/constants";

type Locale = "ar" | "en";

interface LocaleState {
  locale: Locale;
  setLocale: (locale: Locale) => void;
}

export const useLocaleStore = create<LocaleState>()(
  persist(
    (set) => ({
      locale: "ar",
      setLocale: (locale) => {
        set({ locale });
        // Keep the server-side cookie in sync so next-intl's request config
        // picks up the new locale on the next server render.
        if (typeof document !== "undefined") {
          const maxAge = 365 * 24 * 60 * 60;
          document.cookie = `${LOCALE_COOKIE}=${locale};path=/;max-age=${maxAge};SameSite=Lax`;
          window.location.reload();
        }
      },
    }),
    { name: "ea-locale" }
  )
);
