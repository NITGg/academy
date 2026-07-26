import { callMoodleRest, callAcademyApi } from "@/lib/moodle-server";
import type { Course } from "@/features/courses/types";

export interface Teacher {
  userId: number;
  fullName: string;
  photoUrl?: string;
}

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
  price: number;
}

export interface Subscription {
  id: number;
  name: string;
  durationDays: number;
  price: number;
}

export interface HomeDashboardData {
  courses: Course[];
  teachers: Teacher[];
  programs: Program[];
  packages: Package[];
  subscriptions: Subscription[];
}

export async function getHomeDashboardData(): Promise<HomeDashboardData> {
  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  if (!adminToken) throw new Error("Admin token not configured");

  // Fetch all 5 sections in parallel
  const [coursesRes, teachersRes, programsRes, packagesRes, subscriptionsRes] =
    await Promise.allSettled([
      callMoodleRest<{ courses?: Course[] } | Course[]>({
        functionName: "core_course_get_courses_by_field",
        token: adminToken,
      }),
      callAcademyApi<any>("get_all_teachers", { page: 1, perpage: 10 }),
      callAcademyApi<any>("get_catalogue_programs"),
      callAcademyApi<any>("get_available_packages"),
      callAcademyApi<any>("get_available_subscriptions"),
    ]);

  // Extract Courses safely
  let courses: Course[] = [];
  if (coursesRes.status === "fulfilled") {
    const raw = coursesRes.value;
    courses = (Array.isArray(raw) ? raw : raw?.courses ?? []).filter(
      (c) => c.id !== 1
    );
  }

  // Helper to extract array safely from API responses
  const extractArray = <T>(res: PromiseSettledResult<any>, key?: string): T[] => {
    if (res.status !== "fulfilled" || !res.value) return [];
    const val = res.value;
    if (Array.isArray(val)) return val;
    if (key && Array.isArray(val[key])) return val[key];
    if (Array.isArray(val.teachers)) return val.teachers;
    if (Array.isArray(val.data)) return val.data;
    if (Array.isArray(val.items)) return val.items;
    return [];
  };

  return {
    courses,
    teachers: extractArray<Teacher>(teachersRes, "teachers"),
    programs: extractArray<Program>(programsRes, "programs"),
    packages: extractArray<Package>(packagesRes, "packages"),
    subscriptions: extractArray<Subscription>(subscriptionsRes, "subscriptions"),
  };
}