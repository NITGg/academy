import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Returns a full application URL or path respecting NEXT_PUBLIC_APP_URL and local dev environment.
 */
export function getAppUrl(path: string = ""): string {
  const cleanPath = path.startsWith("/") ? path : `/${path}`;
  if (typeof window !== "undefined") {
    if (
      window.location.hostname === "localhost" ||
      window.location.hostname === "127.0.0.1"
    ) {
      return `${window.location.origin}${cleanPath}`;
    }

    const envAppUrl = process.env.NEXT_PUBLIC_APP_URL;
    if (envAppUrl) {
      return `${envAppUrl.replace(/\/$/, "")}${cleanPath}`;
    }
  }
  return cleanPath;
}

