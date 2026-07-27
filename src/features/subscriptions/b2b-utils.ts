// Client-safe helpers for B2B invitation links.
//
// The backend (`b2b_generate_invite`) returns a full URL pointing at the Moodle
// join page: `${MOODLE_BASE_URL}/local/academy/b2b_join.php?t=<token>`.
// We keep only the token and rebuild the shareable link so it opens THIS site's
// own join route (`/b2b/join?t=<token>`), which handles login + join in-app.

/** Pull the raw `t` invitation token out of any invite URL. */
export function extractInviteToken(url: string): string | null {
  if (!url) return null;
  try {
    return new URL(url).searchParams.get("t");
  } catch {
    const m = url.match(/[?&]t=([^&]+)/);
    return m ? decodeURIComponent(m[1]) : null;
  }
}

/**
 * The origin this frontend is served from. Prefers the live browser origin
 * (always correct in dev + prod), falling back to `NEXT_PUBLIC_APP_URL` for SSR.
 * Domain is therefore never hard-coded — it comes from the runtime / env.
 */
export function getAppOrigin(): string {
  if (typeof window !== "undefined") return window.location.origin;
  return (process.env.NEXT_PUBLIC_APP_URL ?? "").replace(/\/$/, "");
}

/** Build the in-app shareable invitation link from the backend-issued URL. */
export function buildFrontendInviteLink(backendUrl: string): string {
  const token = extractInviteToken(backendUrl);
  if (!token) return backendUrl; // fall back to the backend link if parsing fails
  const origin = getAppOrigin();
  return `${origin}/b2b/join?t=${encodeURIComponent(token)}`;
}
