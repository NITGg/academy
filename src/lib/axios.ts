// This file is intentionally thin — in Next.js App Router, all Moodle calls
// go through server-side Route Handlers or Server Actions (never directly from
// the browser). Client components call our own /api/* routes instead.
// See docs/mobile-reference/nextjs-web-port-reference.md §8 (security).

import axios from "axios";

// Helper to determine the correct base URL
const getBaseUrl = () => {
  const basePath = process.env.NEXT_PUBLIC_BASE_PATH || "";

  // If running in the browser, relative path `${basePath}/api` works under subpaths
  if (typeof window !== "undefined") {
    return `${basePath}/api`;
  }

  // If running on the Node server (e.g. inside Server Components during SSR)
  const host = process.env.NEXT_PUBLIC_APP_URL || "http://localhost:3000";
  if (basePath && host.endsWith(basePath)) {
    return `${host}/api`;
  }
  return `${host}${basePath}/api`;
};

// Internal client — calls our own Next.js API routes, not Moodle directly.
export const apiClient = axios.create({
  baseURL: getBaseUrl(),
  headers: { "Content-Type": "application/json" },
  withCredentials: true,
});
