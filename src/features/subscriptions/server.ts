import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApi } from "@/lib/moodle-server";
import type { AvailableSubscription, MySubscription, SubscriptionPaymentRecord } from "./types";

export async function getAvailableSubscriptions(): Promise<AvailableSubscription[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return [];
  try {
    return await callAcademyApi<AvailableSubscription[]>("get_available_subscriptions", {}, token, lang);
  } catch {
    return [];
  }
}

export async function getMySubscriptions(wstoken: string): Promise<MySubscription[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  try {
    return await callAcademyApi<MySubscription[]>("get_my_subscriptions", {}, wstoken, lang);
  } catch {
    return [];
  }
}

export async function getSubscriptionPaymentHistory(wstoken: string): Promise<SubscriptionPaymentRecord[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  try {
    return await callAcademyApi<SubscriptionPaymentRecord[]>("get_subscription_payment_history", {}, wstoken, lang);
  } catch {
    return [];
  }
}
