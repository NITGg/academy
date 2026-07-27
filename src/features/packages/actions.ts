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

export interface PackageDiscountPreview {
  original: number;
  final: number;
  discount: number;
  offerName?: string;
  offerDiscount: number;
  couponDiscount: number;
  couponCode?: string;
  couponError?: string;
}

/** Start a Kashier checkout for a Flex package. */
export async function startPackageCheckout(
  packageId: number,
  couponCode?: string,
): Promise<CheckoutResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const returnUrl = await getRefererUrl();

  try {
    const result = await callAcademyApi<{ checkout_url: string }>(
      "create_package_checkout",
      {
        packageid: packageId,
        ...(couponCode?.trim() ? { coupon_code: couponCode.trim() } : {}),
        ...(returnUrl ? { return_url: returnUrl } : {}),
      },
      session.wstoken,
      lang,
    );
    return { checkoutUrl: result.checkout_url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر بدء عملية الدفع";
    return { error: msg };
  }
}

/** Live price preview for a package, applying automatic offers plus optional coupon. */
export async function previewPackageDiscount(
  packageId: number,
  couponCode?: string,
): Promise<{ data?: PackageDiscountPreview; error?: string; needsAuth?: boolean }> {
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
        item_type: "package",
        item_id: packageId,
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
