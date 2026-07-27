import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApi, callAcademyApiGet } from "@/lib/moodle-server";
import type { Teacher, TeachersResponse, LessonSettings } from "./types";

export async function getTeachers(opts?: {
  search?: string;
  page?: number;
}): Promise<{ teachers: Teacher[]; total: number }> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return { teachers: [], total: 0 };

  try {
    const data = await callAcademyApi<TeachersResponse>(
      "get_all_teachers",
      {
        page: opts?.page ?? 0,
        perpage: 200,
        search: opts?.search ?? "",
        approved: 1,
      },
      token,
      lang
    );

    return {
      teachers: data.teachers ?? [],
      total: data.total ?? 0,
    };
  } catch (error) {
    console.error("Failed to fetch teachers:", error);
    return { teachers: [], total: 0 };
  }
}

export async function getTeacher(id: number): Promise<Teacher | null> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return null;

  try {
    const data = await callAcademyApi<TeachersResponse>(
      "get_all_teachers",
      { page: 0, perpage: 200, approved: 1 },
      token,
      lang
    );
    return data.teachers?.find((t) => t.userid === id) ?? null;
  } catch (error) {
    console.error("Failed to fetch teacher:", error);
    return null;
  }
}

export async function getLessonSettings(): Promise<LessonSettings> {
  const defaults: LessonSettings = {
    min_booking_minutes: 60,
    cancel_deadline_minutes: 120,
    update_deadline_minutes: 120,
    start_allowed_minutes: 30,
    absence_report_minutes: 15,
  };

  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return defaults;

  try {
    const data = await callAcademyApiGet<LessonSettings>(
      "get_lesson_settings",
      {},
      token
    );
    return { ...defaults, ...data };
  } catch {
    return defaults;
  }
}