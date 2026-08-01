import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApi, callAcademyApiGet, callMoodleRest } from "@/lib/moodle-server";
import type { Teacher, TeachersResponse, LessonSettings } from "./types";

export async function getTeachers(opts?: {
  search?: string;
  page?: number;
  categoryid?: number;
  year?: string;
}): Promise<{ teachers: Teacher[]; total: number }> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return { teachers: [], total: 0 };

  try {
    const params: Record<string, string | number | undefined> = {
      page: opts?.page ?? 0,
      perpage: 200,
      search: opts?.search ?? "",
      approved: 1,
    };
    if (opts?.categoryid) params.categoryid = opts.categoryid;
    if (opts?.year) params.year = opts.year;

    const data = await callAcademyApi<TeachersResponse>(
      "get_all_teachers",
      params,
      token,
      lang
    );

    return {
      teachers: data.teachers ?? [],
      total: data.total ?? 0,
    };
  } catch (error) {
    console.warn("Failed to fetch teachers:", error);
    return { teachers: [], total: 0 };
  }
}

export interface TeacherCategory {
  id: number;
  name: string;
}

export async function getTeacherCategories(): Promise<TeacherCategory[]> {
  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return [];
  try {
    const cats = await callMoodleRest<TeacherCategory[]>({
      functionName: "core_course_get_categories",
      useAdminToken: true,
    });
    return Array.isArray(cats) ? cats.filter((c) => c.id !== 0) : [];
  } catch {
    return [];
  }
}

export async function getAcademicYears(): Promise<string[]> {
  try {
    const fields = await callMoodleRest<Array<{
      shortname: string;
      options?: string[];
    }>>({
      functionName: "local_profilefields_get_profile_fields",
      useAdminToken: true,
    });
    const yearField = Array.isArray(fields)
      ? fields.find((f) => f.shortname === "year")
      : null;
    return yearField?.options ?? [];
  } catch {
    return [];
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
    console.warn("Failed to fetch teacher:", error);
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