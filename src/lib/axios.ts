// This file is intentionally thin — in Next.js App Router, all Moodle calls
// go through server-side Route Handlers or Server Actions (never directly from
// the browser). Client components call our own /api/* routes instead.
// See docs/mobile-reference/nextjs-web-port-reference.md §8 (security).

import axios from "axios";

// Internal client — calls our own Next.js API routes, not Moodle directly.
export const apiClient = axios.create({
  baseURL: "/api",
  headers: { "Content-Type": "application/json" },
  withCredentials: true,
});
