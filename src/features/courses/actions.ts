"use server";

import { revalidatePath } from "next/cache";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callMoodleRest, callAcademyApi, callAcademyApiGet } from "@/lib/moodle-server";
import { getMySubscriptions } from "@/features/subscriptions/server";
import { getMyPrograms, getProgramDetails } from "@/features/programs/server";
import type { ProgramContentItem } from "@/features/programs/types";
import { getReturnUrl } from "@/lib/referer";

function extractCourseIdsFromProgramContent(items: ProgramContentItem[]): number[] {
  const ids: number[] = [];
  for (const item of items) {
    if (item.courseid) ids.push(Number(item.courseid));
    if (item.children && Array.isArray(item.children)) {
      ids.push(...extractCourseIdsFromProgramContent(item.children));
    }
  }
  return ids;
}

export async function enrollFree(courseId: number): Promise<{ error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session) return { needsAuth: true };

  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  if (!adminToken) return { error: "Server configuration error" };

  // Fail-closed guard: never free-enrol into a course that has a price. The button
  // should only appear for free courses, but a stale/inconsistent card must not be
  // able to bypass payment. Confirm is_free with the STUDENT token before enrolling.
  try {
    const access = await callMoodleRest<{ is_free: boolean; is_enrolled: boolean }>({
      functionName: "local_payments_get_course_access",
      token: session.wstoken,
      params: { courseid: courseId },
    });
    if (access.is_enrolled) {
      revalidatePath(`/courses/${courseId}`);
      return {};
    }
    if (!access.is_free) {
      return { error: "هذا الكورس مدفوع — لا يمكن التسجيل مجاناً" };
    }
  } catch {
    // Couldn't verify → refuse rather than risk a free enrolment into a paid course
    return { error: "تعذّر التحقق من حالة الكورس، حاول مرة أخرى" };
  }

  try {
    await callMoodleRest({
      functionName: "enrol_manual_enrol_users",
      token: adminToken,
      params: {
        "enrolments[0][userid]": session.user.id,
        "enrolments[0][roleid]": 5,
        "enrolments[0][courseid]": courseId,
      },
    });
  } catch (err) {
    const msg = err instanceof Error ? err.message : "Enrollment failed";
    return { error: msg };
  }

  revalidatePath(`/courses/${courseId}`);
  return {};
}

/**
 * Enrol the current user into a course that one of their active subscriptions or programs covers.
 */
export async function enrollViaSubscription(
  courseId: number,
): Promise<{ error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  if (!adminToken) return { error: "Server configuration error" };

  let timeend = 0;
  let isCovered = false;

  try {
    const subs = await getMySubscriptions(session.wstoken);
    const coveringSub = subs.find(
      (s) => s.status === "active" && (s.courses ?? []).some((c) => c.id === courseId),
    );
    if (coveringSub) {
      isCovered = true;
      timeend = coveringSub.expires_at ?? 0;
    } else {
      // Check program coverage
      const myProgs = await getMyPrograms(session.wstoken);
      for (const prog of myProgs) {
        const details = await getProgramDetails(prog.id, session.wstoken);
        if (details?.content) {
          const courseIds = extractCourseIdsFromProgramContent(details.content);
          if (courseIds.includes(courseId)) {
            isCovered = true;
            break;
          }
        }
      }
    }

    if (!isCovered) {
      return { error: "هذا الكورس غير مشمول بأي من اشتراكاتك أو برامجك النشطة" };
    }
  } catch {
    return { error: "تعذّر التحقق من التغطية، حاول مرة أخرى" };
  }

  // Already enrolled → nothing to do.
  try {
    const access = await callMoodleRest<{ is_enrolled: boolean }>({
      functionName: "local_payments_get_course_access",
      token: session.wstoken,
      params: { courseid: courseId },
    });
    if (access.is_enrolled) {
      revalidatePath(`/courses/${courseId}`);
      return {};
    }
  } catch {
    // couldn't confirm — proceed to enrol (manual enrol is idempotent)
  }

  try {
    const params: Record<string, string | number> = {
      "enrolments[0][userid]": session.user.id,
      "enrolments[0][roleid]": 5, // student
      "enrolments[0][courseid]": courseId,
      "enrolments[0][timestart]": Math.floor(Date.now() / 1000),
    };
    // Bound the enrolment to the subscription; 0 means "no expiry" so we omit timeend.
    if (timeend > 0) params["enrolments[0][timeend]"] = timeend;

    await callMoodleRest({
      functionName: "enrol_manual_enrol_users",
      token: adminToken,
      params,
    });
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر التسجيل عبر الاشتراك";
    return { error: msg };
  }

  revalidatePath(`/courses/${courseId}`);
  revalidatePath("/courses");
  revalidatePath("/");
  return {};
}

/**
 * Complete enrolment for a course that the user has ALREADY purchased (is_purchased = true)
 * but for which Moodle enrolment record was missing or pending.
 */
export async function enrollPurchased(
  courseId: number,
): Promise<{ error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  if (!adminToken) return { error: "Server configuration error" };

  try {
    const access = await callMoodleRest<{ is_purchased: boolean; is_enrolled: boolean }>({
      functionName: "local_payments_get_course_access",
      token: session.wstoken,
      params: { courseid: courseId },
    });
    if (access.is_enrolled) {
      revalidatePath(`/courses/${courseId}`);
      revalidatePath("/courses");
      return {};
    }
    if (!access.is_purchased) {
      return { error: "لم يتم العثور على عملية شراء لهذا الكورس" };
    }
  } catch {
    return { error: "تعذّر التحقق من حالة الشراء، حاول مرة أخرى" };
  }

  try {
    await callMoodleRest({
      functionName: "enrol_manual_enrol_users",
      token: adminToken,
      params: {
        "enrolments[0][userid]": session.user.id,
        "enrolments[0][roleid]": 5,
        "enrolments[0][courseid]": courseId,
      },
    });
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر إكمال التسجيل";
    return { error: msg };
  }

  revalidatePath(`/courses/${courseId}`);
  revalidatePath("/courses");
  revalidatePath("/");
  return {};
}

export async function startCourseCheckout(
  courseId: number,
  couponCode?: string,
): Promise<{ checkoutUrl?: string; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session) return { needsAuth: true };

  const locale = await getLocale();
  const returnUrl = await getReturnUrl(`/courses/${courseId}`);

  try {
    const result = await callMoodleRest<{
      order_id: string;
      checkout_url: string;
      expires_at: number;
      provider: string;
      transaction_id: number;
    }>({
      functionName: "local_payments_create_checkout",
      token: session.wstoken,
      params: {
        courseid: courseId,
        lang: locale === "ar" ? "ar" : "en",
        country: "EG",
        ...(couponCode?.trim() ? { coupon_code: couponCode.trim() } : {}),
        ...(returnUrl ? { return_url: returnUrl } : {}),
      },
    });

    return { checkoutUrl: result.checkout_url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "Checkout failed";
    return { error: msg };
  }
}

// modname → the Moodle "view" web-service function + its id param name.
const VIEW_FUNCTIONS: Record<string, { fn: string; param: string }> = {
  resource: { fn: "mod_resource_view_resource", param: "resourceid" },
  url: { fn: "mod_url_view_url", param: "urlid" },
  page: { fn: "mod_page_view_page", param: "pageid" },
  folder: { fn: "mod_folder_view_folder", param: "folderid" },
  book: { fn: "mod_book_view_book", param: "bookid" },
  lesson: { fn: "mod_lesson_view_lesson", param: "lessonid" },
  scorm: { fn: "mod_scorm_view_scorm", param: "scormid" },
  quiz: { fn: "mod_quiz_view_quiz", param: "quizid" },
};

/**
 * Fire Moodle's "viewed" event for a module so on-view completion triggers.
 * Best-effort — the app renders content itself, so a failure here must not block
 * the user opening the content.
 *
 * Two paths, so it works for EVERY module type (not just the handful with a
 * per-type "view" web-service function):
 *  1. Known type with a `mod_*_view_*` function AND an instance id → call it.
 *     This uses only core Moodle web services, so it keeps working regardless of
 *     the custom backend.
 *  2. Anything else (custom `resource2` / `testnew` video modules, or a known
 *     type missing its instance id) → fall back to the generic `local_academy`
 *     endpoint keyed on `cmid`, which records the view for any module type.
 */
export async function markModuleViewed(
  modname: string,
  cmid: number,
  instance?: number,
): Promise<{ ok: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { ok: false };

  const entry = VIEW_FUNCTIONS[modname];
  if (entry && instance) {
    try {
      await callMoodleRest({
        functionName: entry.fn,
        token: session.wstoken,
        params: { [entry.param]: instance },
      });
      return { ok: true };
    } catch {
      // Fall through to the generic endpoint below.
    }
  }

  // Generic fallback — covers module types without a per-type view WS function.
  if (!cmid) return { ok: false };
  try {
    await callAcademyApi("mark_activity_viewed", { cmid }, session.wstoken);
    return { ok: true };
  } catch {
    return { ok: false };
  }
}

export interface CourseDiscountPreview {
  original: number;
  final: number;
  discount: number;
  offerName?: string;
  offerDiscount: number;
  couponDiscount: number;
  couponCode?: string;
  couponError?: string;
}

/**
 * Live price preview for a course, applying automatic offers plus an optional
 * coupon. Note: an invalid coupon still returns success with `couponError` set
 * and the offer-only price — trust `final`, never compute prices client-side.
 */
export async function previewCourseDiscount(
  courseId: number,
  couponCode?: string,
): Promise<{ data?: CourseDiscountPreview; error?: string; needsAuth?: boolean }> {
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
        item_type: "course",
        item_id: courseId,
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
