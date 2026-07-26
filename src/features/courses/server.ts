import "server-only";
import { getLocale } from "next-intl/server";
import { callMoodleRest } from "@/lib/moodle-server";
import { parseMlang } from "@/lib/mlang";
import type { Course, CourseCategory } from "./types";

interface MoodleCoursesResponse {
  courses?: Course[];
}

export interface CoursesPageData {
  categories: CourseCategory[];
  courses: Course[];
}

export async function getCoursesPageData(opts?: {
  categoryId?: string;
  search?: string;
}): Promise<CoursesPageData> {
  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) throw new Error("Admin token not configured");

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  const [categoriesResult, coursesResult] = await Promise.allSettled([
    callMoodleRest<CourseCategory[]>({
      functionName: "core_course_get_categories",
      token,
    }),
    callMoodleRest<MoodleCoursesResponse | Course[]>({
      functionName: "core_course_get_courses_by_field",
      token,
    }),
  ]);

  const rawCourses: Course[] =
    coursesResult.status === "fulfilled"
      ? Array.isArray(coursesResult.value)
        ? coursesResult.value
        : (coursesResult.value as MoodleCoursesResponse)?.courses ?? []
      : [];

  const allCourses = rawCourses.filter((c) => c.id !== 1);

  // Build category list: prefer Moodle's category API, fall back to deriving from courses
  let categories: CourseCategory[] = [];
  if (categoriesResult.status === "fulfilled" && Array.isArray(categoriesResult.value) && categoriesResult.value.length > 0) {
    categories = categoriesResult.value.map((cat) => ({
      ...cat,
      name: parseMlang(cat.name, lang),
    }));
  } else {
    // Derive unique categories from the course list
    const seen = new Map<number, CourseCategory>();
    for (const c of allCourses) {
      if (c.categoryid && !seen.has(c.categoryid)) {
        seen.set(c.categoryid, {
          id: c.categoryid,
          name: parseMlang(c.categoryname ?? String(c.categoryid), lang),
        });
      }
    }
    categories = Array.from(seen.values()).sort((a, b) => a.id - b.id);
  }

  let courses = allCourses;

  if (opts?.categoryId) {
    courses = courses.filter((c) => String(c.categoryid) === opts.categoryId);
  }

  if (opts?.search) {
    const q = opts.search.toLowerCase().trim();
    courses = courses.filter(
      (c) =>
        c.fullname?.toLowerCase().includes(q) ||
        c.shortname?.toLowerCase().includes(q)
    );
  }

  return { categories, courses };
}
