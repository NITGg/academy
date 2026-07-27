"use server";

import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callAcademyApi } from "@/lib/moodle-server";
import { getRefererUrl } from "@/lib/referer";

export interface CheckoutResult {
  checkoutUrl?: string;
  error?: string;
  needsAuth?: boolean;
}

/** Start a Kashier checkout for a Flex package. */
export async function startPackageCheckout(packageId: number): Promise<CheckoutResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const returnUrl = await getRefererUrl();

  try {
    const result = await callAcademyApi<{ checkout_url: string }>(
      "create_package_checkout",
      { packageid: packageId, ...(returnUrl ? { return_url: returnUrl } : {}) },
      session.wstoken,
      lang,
    );
    return { checkoutUrl: result.checkout_url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر بدء عملية الدفع";
    return { error: msg };
  }
}
