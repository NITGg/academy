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
