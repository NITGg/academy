"use server";

import { revalidatePath } from "next/cache";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callMoodleRest } from "@/lib/moodle-server";

export async function enrollFree(courseId: number): Promise<{ error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session) return { needsAuth: true };

  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  if (!adminToken) return { error: "Server configuration error" };

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

export async function startCourseCheckout(courseId: number): Promise<{ checkoutUrl?: string; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session) return { needsAuth: true };

  const locale = await getLocale();

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
      },
    });

    return { checkoutUrl: result.checkout_url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "Checkout failed";
    return { error: msg };
  }
}
