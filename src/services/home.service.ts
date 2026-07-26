import { callMoodleRest, callAcademyApi } from "@/lib/moodle-server";
import type { Course } from "@/features/courses/types";
import type { Teacher } from "@/features/teachers/types";

export interface Program {
  id: number;
  name: string;
  owned?: boolean;
}

export interface Package {
  id: number;
  name: string;
  description?: string;
  flexCount: number;
  flex_count?: number;
  price: number;
}

export interface Subscription {
  id: number;
  name: string;
  durationDays: number;
  duration_days?: number;
  price: number;
}

export interface HomeDashboardData {
  courses: Course[];
  teachers: Teacher[];
  programs: Program[];
  packages: Package[];
  subscriptions: Subscription[];
}

function moodlePublicUrl(url?: string): string | undefined {
  if (!url) return undefined;
  return url.replace("/webservice/pluginfile.php", "/pluginfile.php");
}

export async function getHomeDashboardData(): Promise<HomeDashboardData> {
  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  if (!adminToken) throw new Error("Admin token not configured");

  const [coursesRes, teachersRes, programsRes, packagesRes, subscriptionsRes] =
    await Promise.allSettled([
      callMoodleRest<{ courses?: Course[] } | Course[]>({
        functionName: "core_course_get_courses_by_field",
        token: adminToken,
      }),
      callAcademyApi<{ total: number; teachers: Teacher[] }>(
        "get_all_teachers",
        { page: 1, perpage: 20 },
      ),
      callAcademyApi<Program[]>("get_catalogue_programs"),
      callAcademyApi<Package[]>("get_available_packages"),
      callAcademyApi<Subscription[]>("get_available_subscriptions"),
    ]);

  // Courses — map overviewfiles → courseimage
  let courses: Course[] = [];
  if (coursesRes.status === "fulfilled") {
    const raw = coursesRes.value as any;
    const list: any[] = Array.isArray(raw) ? raw : raw?.courses ?? [];
    courses = list
      .filter((c) => c.id !== 1)
      .map((c) => ({
        ...c,
        courseimage:
          c.courseimage ||
          moodlePublicUrl(c.overviewfiles?.[0]?.fileurl),
      }));
  }

  // Teachers — API returns snake_case; use the Teacher type from features/teachers/types
  let teachers: Teacher[] = [];
  if (teachersRes.status === "fulfilled" && teachersRes.value) {
    const val = teachersRes.value as any;
    const list: any[] = Array.isArray(val)
      ? val
      : val?.teachers ?? val?.data?.teachers ?? [];
    teachers = list;
  }

  // Helper for array responses wrapped in { status, data: [...] }
  function extractList<T>(res: PromiseSettledResult<any>): T[] {
    if (res.status !== "fulfilled" || !res.value) return [];
    const v = res.value;
    if (Array.isArray(v)) return v;
    return [];
  }

  const rawPackages = extractList<any>(packagesRes);
  const packages: Package[] = rawPackages.map((p) => ({
    ...p,
    id: Number(p.id),
    flexCount: Number(p.flex_count ?? p.flexCount ?? 0),
    price: Number(p.price),
  }));

  const rawSubs = extractList<any>(subscriptionsRes);
  const subscriptions: Subscription[] = rawSubs.map((s) => ({
    ...s,
    id: Number(s.id),
    durationDays: Number(s.duration_days ?? s.durationDays ?? 0),
    price: Number(s.price),
  }));

  const rawPrograms = extractList<any>(programsRes);
  const programs: Program[] = rawPrograms.map((p) => ({
    ...p,
    id: Number(p.id),
  }));

  return { courses, teachers, programs, packages, subscriptions };
}
