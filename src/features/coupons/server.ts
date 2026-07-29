import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApi } from "@/lib/moodle-server";
import type { AvailableCoupon } from "./types";

export async function getAvailableCoupons(wstoken?: string): Promise<AvailableCoupon[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const activeToken = wstoken || process.env.MOODLE_ADMIN_TOKEN;

  if (!activeToken) return [];

  try {
    return await callAcademyApi<AvailableCoupon[]>(
      "get_available_coupons",
      {},
      activeToken,
      lang
    );
  } catch (err) {
    console.error("[getAvailableCoupons Error]:", err);
    return [];
  }
}
