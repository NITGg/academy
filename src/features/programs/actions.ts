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

/** Start a Kashier checkout for a Program. */
export async function startProgramCheckout(programId: number): Promise<CheckoutResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const returnUrl = await getRefererUrl();

  try {
    const result = await callAcademyApi<{ checkout_url: string }>(
      "create_program_checkout",
      { programid: programId, ...(returnUrl ? { return_url: returnUrl } : {}) },
      session.wstoken,
      lang,
    );
    return { checkoutUrl: result.checkout_url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر بدء عملية الدفع للبرنامج";
    return { error: msg };
  }
}

/** Join a free program. */
export async function joinFreeProgram(programId: number): Promise<{ success: boolean; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true, success: false };

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  try {
    await callAcademyApi(
      "join_program",
      { programid: programId },
      session.wstoken,
      lang,
    );
    return { success: true };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر الانضمام إلى البرنامج";
    return { success: false, error: msg };
  }
}

/** Open program certificate URL. */
export async function openProgramCertificateAction(
  certificateId: number
): Promise<{ url?: string; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  try {
    const res = await callAcademyApi<{ url: string }>(
      "open_certificate",
      { certificateid: certificateId },
      session.wstoken,
      lang
    );
    return { url: res.url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر فتح الشهادة";
    return { error: msg };
  }
}

