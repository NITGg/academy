import "server-only";
import { headers } from "next/headers";

/**
 * The exact front-end page a server action was invoked from.
 *
 * Server actions are same-origin POSTs, so the browser sends the full page URL in the
 * `Referer` header (under the default `strict-origin-when-cross-origin` policy same-origin
 * requests keep the path + query). We forward this to the payment checkout as `return_url`
 * so the payment callback can send the user back to precisely where they started.
 * Returns "" when unavailable — the backend then falls back to an item-type landing page.
 */
export async function getRefererUrl(): Promise<string> {
  try {
    return (await headers()).get("referer") ?? "";
  } catch {
    return "";
  }
}

/**
 * An absolute `return_url` for the payment gateway that is **guaranteed non-empty** and
 * always points back into THIS Next.js frontend on the current domain.
 *
 * Why this exists: when the `Referer` header is missing, `getRefererUrl()` returns "" and
 * the backend payment callback falls back to its own success page on the Moodle domain
 * (`/local/payments/callback.php`) — dumping the user on the "old" site instead of back in
 * the app. We prefer the referer (returns the user to the exact page they bought from), and
 * fall back to an explicit in-app page (`fallbackPath`) built from the live request host so
 * the domain is never hard-coded and never the wrong one.
 *
 * @param fallbackPath in-app path to land on when the referer is unavailable, e.g.
 *   `/courses/12`, `/programs/4`, `/packages`, `/subscriptions`.
 */
export async function getReturnUrl(fallbackPath: string): Promise<string> {
  let h: Awaited<ReturnType<typeof headers>> | null = null;
  try {
    h = await headers();
  } catch {
    h = null;
  }

  // Primary: the page the checkout was started from (always a current-domain URL).
  const referer = h?.get("referer");
  if (referer) return referer;

  const cleanPath = fallbackPath.startsWith("/") ? fallbackPath : `/${fallbackPath}`;
  const basePath = process.env.NEXT_PUBLIC_BASE_PATH || "";

  // Fallback: rebuild an absolute URL on the current origin from the request headers.
  const host = h?.get("x-forwarded-host") ?? h?.get("host");
  if (host) {
    const proto = h?.get("x-forwarded-proto") ?? "https";
    return `${proto}://${host}${basePath}${cleanPath}`;
  }

  // Last resort (e.g. no request headers): the configured public app URL, which
  // already includes any base path.
  const envUrl = (process.env.NEXT_PUBLIC_APP_URL ?? "").replace(/\/$/, "");
  return envUrl ? `${envUrl}${cleanPath}` : "";
}
