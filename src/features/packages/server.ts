import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApi } from "@/lib/moodle-server";
import type { AvailablePackage, MyPackage, PackagePaymentRecord } from "./types";

export async function getAvailablePackages(): Promise<AvailablePackage[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return [];
  try {
    return await callAcademyApi<AvailablePackage[]>("get_available_packages", {}, token, lang);
  } catch {
    return [];
  }
}

export async function getMyPackages(wstoken: string): Promise<MyPackage[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  try {
    return await callAcademyApi<MyPackage[]>("get_my_packages", {}, wstoken, lang);
  } catch {
    return [];
  }
}

export async function getPackagePaymentHistory(wstoken: string): Promise<PackagePaymentRecord[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  try {
    return await callAcademyApi<PackagePaymentRecord[]>("get_payment_history", {}, wstoken, lang);
  } catch {
    return [];
  }
}
