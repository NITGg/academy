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

/**
 * Fetch a single lesson (with its live `jitsi_session` when joinable). Used by the
 * in-site meeting room page — the JWT inside `jitsi_session` is minted fresh here so
 * the student joins Jitsi on our own domain instead of the Moodle view.php page.
 */
export async function getLesson(
  id: number,
  wstoken: string,
): Promise<Lesson | null> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  try {
    return await callAcademyApiGet<Lesson>("get_lesson", { lessonid: id }, wstoken, lang);
  } catch {
    return null;
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
