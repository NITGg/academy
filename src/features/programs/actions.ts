"use server";

import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callAcademyApi, callAcademyApiGet } from "@/lib/moodle-server";
import { getRefererUrl } from "@/lib/referer";

export interface CheckoutResult {
  checkoutUrl?: string;
  error?: string;
  needsAuth?: boolean;
}

export interface ProgramDiscountPreview {
  original: number;
  final: number;
  discount: number;
  offerName?: string;
  offerDiscount: number;
  couponDiscount: number;
  couponCode?: string;
  couponError?: string;
}

/** Start a Kashier checkout for a Program. */
export async function startProgramCheckout(
  programId: number,
  couponCode?: string,
): Promise<CheckoutResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const returnUrl = await getRefererUrl();

  try {
    const result = await callAcademyApi<{ checkout_url: string }>(
      "create_program_checkout",
      {
        programid: programId,
        ...(couponCode?.trim() ? { coupon_code: couponCode.trim() } : {}),
        ...(returnUrl ? { return_url: returnUrl } : {}),
      },
      session.wstoken,
      lang,
    );
    return { checkoutUrl: result.checkout_url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر بدء عملية الدفع للبرنامج";
    return { error: msg };
  }
}

/** Live price preview for a program, applying automatic offers plus optional coupon. */
export async function previewProgramDiscount(
  programId: number,
  couponCode?: string,
): Promise<{ data?: ProgramDiscountPreview; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  try {
    const d = await callAcademyApiGet<{
      original: number;
      offer_name?: string;
      offer_discount?: number;
      coupon_code?: string;
      coupon_discount?: number;
      discount?: number;
      final: number;
      coupon_error?: string;
    }>(
      "preview_discount",
      {
        item_type: "program",
        item_id: programId,
        ...(couponCode?.trim() ? { coupon_code: couponCode.trim() } : {}),
      },
      session.wstoken,
      lang,
    );

    return {
      data: {
        original: Number(d.original),
        final: Number(d.final),
        discount: Number(d.discount ?? 0),
        offerName: d.offer_name || undefined,
        offerDiscount: Number(d.offer_discount ?? 0),
        couponDiscount: Number(d.coupon_discount ?? 0),
        couponCode: d.coupon_code || undefined,
        couponError: d.coupon_error || undefined,
      },
    };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر التحقق من الكوبون";
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

