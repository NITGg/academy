import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApiGet } from "@/lib/moodle-server";
import type { Lesson, FlexTx } from "./types";

export async function getMyLessons(wstoken: string): Promise<Lesson[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  try {
    return await callAcademyApiGet<Lesson[]>("get_my_lessons", { role: "student" }, wstoken, lang);
  } catch {
    return [];
  }
}

export async function getFlexHistory(wstoken: string): Promise<FlexTx[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  try {
    return await callAcademyApiGet<FlexTx[]>("get_flex_history", {}, wstoken, lang);
  } catch {
    return [];
  }
}
