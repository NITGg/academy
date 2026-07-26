import "server-only";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callMoodleRest } from "@/lib/moodle-server";
import { parseMlang } from "@/lib/mlang";
import { MOODLE_BASE_URL } from "@/config/constants";
import type {
  Course,
  CourseSection,
  CoursePrice,
  CourseModule,
  RawCourseTopics,
  RawActivity,
} from "./types";

export interface CourseDetailData {
  course: Course;
  pricing: CoursePrice | null;
  contents: CourseSection[];
}

function normalizeActivity(a: RawActivity, lang: string): CourseModule {
  return {
    id: parseInt(a.id, 10),
    name: parseMlang(a.name ?? "", lang),
    modname: a.modname,
    instance: a.instance ? parseInt(a.instance, 10) : undefined,
    url: a.url,
    visible: a.visible ? 1 : 0,
    uservisible: a.uservisible,
    fileurl: a.fileurl,
    resourcetype: a.resourcetype,
    locked: a.locked,
    availabilityinfo: a.availabilityinfo,
  };
}

function normalizeTopics(raw: RawCourseTopics, lang: string): CourseSection[] {
  const sections: CourseSection[] = [];

  for (const parent of raw.parents ?? []) {
    const parentName = parseMlang(parent.name ?? "", lang);
    const hasTopics = parent.topics && parent.topics.length > 0;

    if (!hasTopics) {
      const modules = (parent.activities ?? [])
        .filter((a) => a.uservisible !== false)
        .map((a) => normalizeActivity(a, lang));
      if (modules.length > 0) {
        sections.push({ id: parseInt(parent.id, 10), name: parentName, modules });
      }
    } else {
      // Parent acts as a heading; its direct activities go into a section of their own
      const directMods = (parent.activities ?? [])
        .filter((a) => a.uservisible !== false)
        .map((a) => normalizeActivity(a, lang));
      if (directMods.length > 0) {
        sections.push({ id: parseInt(parent.id, 10), name: parentName, modules: directMods });
      }
      // Each topic becomes its own section
      for (const topic of parent.topics!) {
        const topicModules = (topic.activities ?? [])
          .filter((a) => a.uservisible !== false)
          .map((a) => normalizeActivity(a, lang));
        if (topicModules.length > 0) {
          sections.push({
            id: parseInt(topic.id, 10),
            name: parseMlang(topic.name ?? "", lang),
            modules: topicModules,
          });
        }
      }
    }
  }

  return sections;
}

export async function getCourseDetail(courseId: number): Promise<CourseDetailData> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  const session = await getSessionFromCookie();
  const token = session?.wstoken ?? process.env.MOODLE_ADMIN_TOKEN;
  if (!token) throw new Error("No token available");

  // Fetch course metadata, pricing, and content tree in parallel
  const [coursesResult, pricingResult, topicsResult] = await Promise.allSettled([
    callMoodleRest<{ courses: Course[] } | Course[]>({
      functionName: "core_course_get_courses_by_field",
      token,
      params: { field: "id", value: courseId },
    }),
    // local_payments_get_course_price — use admin token, country defaults to EG
    session
      ? callMoodleRest<CoursePrice>({
          functionName: "local_payments_get_course_price",
          token: process.env.MOODLE_ADMIN_TOKEN ?? token,
          params: { courseid: courseId, country: "EG" },
        })
      : Promise.resolve(null),
    // getalltopics.php — primary content tree source
    fetch(
      `${MOODLE_BASE_URL}/local/multitopics/getalltopics.php?` +
        new URLSearchParams({
          courseid: String(courseId),
          wstoken: token,
          lang,
        }),
      { cache: "no-store" }
    )
      .then((r) => (r.ok ? (r.json() as Promise<RawCourseTopics>) : Promise.reject(r.status)))
      .catch(() => null as RawCourseTopics | null),
  ]);

  // ── Course metadata ──────────────────────────────────────────────────────────
  if (coursesResult.status === "rejected") throw new Error("Course not found");

  const rawCourses = Array.isArray(coursesResult.value)
    ? coursesResult.value
    : (coursesResult.value as { courses: Course[] }).courses ?? [];

  if (!rawCourses[0]) throw new Error("Course not found");

  const course = { ...rawCourses[0] };
  course.fullname = parseMlang(course.fullname ?? "", lang);
  if (course.shortname) course.shortname = parseMlang(course.shortname, lang);
  if (course.categoryname) course.categoryname = parseMlang(course.categoryname, lang);

  // ── Pricing / access ────────────────────────────────────────────────────────
  const pricing =
    pricingResult.status === "fulfilled" && pricingResult.value
      ? (pricingResult.value as CoursePrice)
      : null;

  // Sync enrollment status onto the course object from pricing data
  if (pricing) {
    course.isFree = pricing.is_free ?? pricing.price === 0;
    course.isEnrolled = pricing.is_enrolled ?? course.isEnrolled;
    if (!course.isFree && pricing.price != null) {
      course.price = pricing.sale_price ?? pricing.price;
    }
  }

  // ── Content tree ─────────────────────────────────────────────────────────────
  let contents: CourseSection[] = [];

  const rawTopics =
    topicsResult.status === "fulfilled" ? topicsResult.value : null;

  if (rawTopics && Array.isArray(rawTopics.parents) && rawTopics.parents.length > 0) {
    contents = normalizeTopics(rawTopics, lang);
  } else {
    // Fallback: core_course_get_contents
    const fallbackResult = await callMoodleRest<CourseSection[]>({
      functionName: "core_course_get_contents",
      token,
      params: { courseid: courseId },
    }).catch(() => [] as CourseSection[]);

    contents = (Array.isArray(fallbackResult) ? fallbackResult : [])
      .filter((s) => s.uservisible !== false)
      .map((section) => ({
        ...section,
        name: parseMlang(section.name ?? "", lang),
        modules: (section.modules ?? [])
          .filter((m) => m.uservisible !== false && m.visible !== 0)
          .map((mod) => ({ ...mod, name: parseMlang(mod.name ?? "", lang) })),
      }));
  }

  return { course, pricing, contents };
}
