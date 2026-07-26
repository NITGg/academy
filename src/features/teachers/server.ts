import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApi } from "@/lib/moodle-server";
import type { Teacher, TeachersResponse } from "./types";

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
      lang,
    );

    return { teachers: data.teachers ?? [], total: data.total ?? 0 };
  } catch {
    return { teachers: [], total: 0 };
  }
}
