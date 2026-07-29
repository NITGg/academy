"use server";

import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callAcademyApi, callAcademyApiGet } from "@/lib/moodle-server";
import { getReturnUrl } from "@/lib/referer";

export interface CheckoutResult {
  checkoutUrl?: string;
  error?: string;
  needsAuth?: boolean;
}

export interface SubscriptionDiscountPreview {
  original: number;
  final: number;
  discount: number;
  offerName?: string;
  offerDiscount: number;
  couponDiscount: number;
  couponCode?: string;
  couponError?: string;
}

/** Start a Kashier checkout for a Subscription (Normal or B2B). */
export async function startSubscriptionCheckout(params: {
  subscriptionId: number;
  type?: "normal" | "b2b";
  seats?: number;
  couponCode?: string;
}): Promise<CheckoutResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  try {
    const payload: Record<string, unknown> = {
      subscriptionid: params.subscriptionId,
      type: params.type ?? "normal",
    };
    if (params.seats) payload.seats = params.seats;
    if (params.couponCode?.trim()) payload.coupon_code = params.couponCode.trim();
    const returnUrl = await getReturnUrl("/subscriptions");
    if (returnUrl) payload.return_url = returnUrl;

    const result = await callAcademyApi<{ checkout_url: string }>(
      "create_subscription_checkout",
      payload,
      session.wstoken,
      lang,
    );
    return { checkoutUrl: result.checkout_url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر بدء عملية الدفع للاشتراك";
    return { error: msg };
  }
}

/** Preview discount / coupon for a subscription. */
export async function previewSubscriptionDiscount(params: {
  subscriptionId: number;
  couponCode?: string;
}): Promise<{ data?: SubscriptionDiscountPreview; error?: string; needsAuth?: boolean }> {
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
        item_type: "subscription",
        item_id: params.subscriptionId,
        ...(params.couponCode?.trim() ? { coupon_code: params.couponCode.trim() } : {}),
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
