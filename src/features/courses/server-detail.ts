import "server-only";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callMoodleRest } from "@/lib/moodle-server";
import { enrichCoursesWithPricing } from "./server";
import { getMySubscriptions } from "@/features/subscriptions/server";
import { parseMlang } from "@/lib/mlang";
import { MOODLE_BASE_URL } from "@/config/constants";
import type {
  Course,
  CourseSection,
  CourseModule,
  RawCourseTopics,
  RawActivity,
} from "./types";

export interface CourseAccess {
  isFree: boolean;
  isEnrolled: boolean;
  isPurchased: boolean;
  hasPendingPayment: boolean;
  /** True when one of the user's active subscriptions unlocks this course (free on-demand enrol). */
  coveredBySubscription: boolean;
}

export interface CourseDetailData {
  course: Course;
  access: CourseAccess;
  contents: CourseSection[];
}

function normalizeActivity(a: RawActivity, lang: "ar" | "en"): CourseModule {
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

function normalizeTopics(raw: RawCourseTopics, lang: "ar" | "en"): CourseSection[] {
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
  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  const userToken = session?.wstoken;
  if (!userToken && !adminToken) throw new Error("No token available");
  // Course metadata + content structure are not user-specific — admin token is fine
  // and lets non-enrolled users preview the outline. Pricing/access below use the
  // STUDENT token so enrolment/purchase reflect the actual viewer (same as the cards).
  const fetchToken = adminToken ?? userToken!;

  // Fetch course metadata, per-user access, and content tree in parallel
  const [coursesResult, accessResult, topicsResult] = await Promise.allSettled([
    callMoodleRest<{ courses: Course[] } | Course[]>({
      functionName: "core_course_get_courses_by_field",
      token: fetchToken,
      params: { field: "id", value: courseId },
    }),
    // Authoritative per-user access (pending-payment flag) — student token only
    userToken
      ? callMoodleRest<{
          is_enrolled: boolean;
          is_purchased: boolean;
          has_pending_payment: boolean;
          is_free: boolean;
        }>({
          functionName: "local_payments_get_course_access",
          token: userToken,
          params: { courseid: courseId },
        })
      : Promise.resolve(null),
    // getalltopics.php — primary content tree source
    fetch(
      `${MOODLE_BASE_URL}/local/multitopics/getalltopics.php?` +
        new URLSearchParams({
          courseid: String(courseId),
          wstoken: fetchToken,
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

  let course: Course = { ...rawCourses[0] };
  course.fullname = parseMlang(course.fullname ?? "", lang);
  if (course.shortname) course.shortname = parseMlang(course.shortname, lang);
  if (course.categoryname) course.categoryname = parseMlang(course.categoryname, lang);

  // ── Pricing / access — SAME code path as the catalog cards, STUDENT token ─────
  [course] = await enrichCoursesWithPricing([course], userToken);

  const rawAccess =
    accessResult.status === "fulfilled" && accessResult.value ? accessResult.value : null;

  const access: CourseAccess = {
    isFree: course.isFree ?? rawAccess?.is_free ?? !course.price,
    isEnrolled: course.isEnrolled ?? rawAccess?.is_enrolled ?? false,
    isPurchased: course.isPurchased ?? rawAccess?.is_purchased ?? false,
    hasPendingPayment: rawAccess?.has_pending_payment ?? false,
    coveredBySubscription: false,
  };

  // Only relevant for a paid course the viewer hasn't accessed yet: is it unlocked by one of
  // their active subscriptions (normal or B2B)? If so we offer a free on-demand enrol instead of buy.
  if (userToken && !access.isEnrolled && !access.isPurchased && !access.isFree) {
    try {
      const subs = await getMySubscriptions(userToken);
      access.coveredBySubscription = subs.some(
        (s) => s.status === "active" && (s.courses ?? []).some((c) => c.id === courseId),
      );
    } catch {
      // best-effort — leave coveredBySubscription false
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
      token: fetchToken,
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

  return { course, access, contents };
}
