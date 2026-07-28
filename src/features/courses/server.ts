import "server-only";
import { getLocale } from "next-intl/server";
import { callMoodleRest } from "@/lib/moodle-server";
import { parseMlang } from "@/lib/mlang";
import type { Course, CourseCategory } from "./types";

export interface EnrolledCourse {
  id: number;
  fullname: string;
  shortname?: string;
  categoryid?: number;
  progress?: number | null;
  completed?: boolean;
  overviewfiles?: Array<{ fileurl: string; mimetype?: string }>;
  startdate?: number;
  enddate?: number;
  isPurchasedNotEnrolled?: boolean;
}

export async function getMyCourses(wstoken: string, userid: number): Promise<EnrolledCourse[]> {
  try {
    const raw = await callMoodleRest<EnrolledCourse[]>({
      functionName: "core_enrol_get_users_courses",
      token: wstoken,
      params: { userid },
    });
    return Array.isArray(raw) ? raw.filter((c) => c.id !== 1) : [];
  } catch {
    return [];
  }
}

/** Raw item shape from local_payments_get_courses_with_pricing. */
interface RawPricedCourse {
  id: number;
  currency?: string;
  price?: number;
  original_price?: number;
  sale_price?: number;
  discount_percentage?: number;
  is_sale_active?: boolean;
  is_free?: boolean;
  is_purchased?: boolean;
  is_enrolled?: boolean;
  offer_name?: string;
}

/**
 * Merge country-resolved pricing, offers, and access flags onto a set of courses
 * in one bulk call. Uses the user's token when available so is_enrolled /
 * is_purchased reflect the current user; falls back to the admin token for guests.
 * Best-effort — on failure the courses are returned unchanged.
 */
export async function enrichCoursesWithPricing<T extends Course>(
  courses: T[],
  wstoken?: string,
): Promise<T[]> {
  if (courses.length === 0) return courses;
  const token = wstoken ?? process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return courses;
  // Without a user token the endpoint runs as admin — its is_enrolled/is_purchased
  // would reflect the admin, not the (guest) viewer. Pricing/is_free are course-level
  // and still correct; we force per-user access flags to false for guests.
  const isGuest = !wstoken;

  try {
    const res = await callMoodleRest<{ courses?: RawPricedCourse[] } | RawPricedCourse[]>({
      functionName: "local_payments_get_courses_with_pricing",
      token,
      params: {
        field: "ids",
        value: courses.map((c) => c.id).join(","),
        country: "EG",
      },
    });

    const list: RawPricedCourse[] = Array.isArray(res) ? res : res?.courses ?? [];
    const byId = new Map<number, RawPricedCourse>(list.map((p) => [Number(p.id), p]));

    return courses.map((course) => {
      const p = byId.get(course.id);
      if (!p) return course;
      const isFree = p.is_free ?? p.price === 0;
      const effective = p.is_sale_active && p.sale_price != null ? p.sale_price : p.price;
      return {
        ...course,
        currency: p.currency ?? course.currency,
        price: effective ?? course.price,
        originalPrice: p.original_price ?? course.originalPrice,
        discountPercentage: p.discount_percentage ?? course.discountPercentage,
        isSaleActive: p.is_sale_active ?? course.isSaleActive,
        offerName: p.offer_name || course.offerName,
        isFree,
        isEnrolled: isGuest ? false : (p.is_enrolled ?? course.isEnrolled),
        isPurchased: isGuest ? false : (p.is_purchased ?? course.isPurchased),
      };
    });
  } catch {
    return courses;
  }
}

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
  wstoken?: string;
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

  // Merge pricing/offers/access flags onto the visible courses
  courses = await enrichCoursesWithPricing(courses, opts?.wstoken);

  return { categories, courses };
}
