import "server-only";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callMoodleRest } from "@/lib/moodle-server";
import { enrichCoursesWithPricing } from "./server";
import { getMySubscriptions } from "@/features/subscriptions/server";
import { getMyPrograms, getProgramDetails } from "@/features/programs/server";
import { parseMlang } from "@/lib/mlang";
import { MOODLE_BASE_URL } from "@/config/constants";
import type { CoverageType } from "./components/CourseCard";
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
  coverageType: CoverageType | null;
  /** True when subscription or program unlocks this course (free on-demand enrol). */
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
    // NOTE: `a.fileurl` embeds the Moodle token — never forward it to the client.
    // The browser loads files through /api/activity-file (courseId + cmid); only a
    // boolean + the MIME type cross the wire here.
    hasFile: Boolean(a.fileurl),
    resourcetype: a.resourcetype,
    locked: a.locked,
    availabilityinfo: a.availabilityinfo,
    // Completion (drives the "للقيام به: ..." lines and the "منجز" state/button)
    completion: a.completion,
    completionstate: a.completionstate,
    hascompletion: a.hascompletion,
    isautomatic: a.isautomatic,
    completionexpected: a.completionexpected,
    completiondetails: a.completiondetails,
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
  // Content token MUST prefer student's userToken when logged in so Moodle returns
  // per-user completion states (completionstate, completiondetails). Fall back to adminToken.
  const contentToken = userToken ?? adminToken!;
  const metadataToken = adminToken ?? userToken!;

  // Fetch course metadata, per-user access, and content tree in parallel
  const [coursesResult, accessResult, topicsResult] = await Promise.allSettled([
    callMoodleRest<{ courses: Course[] } | Course[]>({
      functionName: "core_course_get_courses_by_field",
      token: metadataToken,
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
          wstoken: contentToken,
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
    coverageType: null,
    coveredBySubscription: false,
  };

  // Only relevant for a paid course the viewer hasn't accessed yet: is it unlocked by one of
  // their active subscriptions or programs?
  if (userToken && !access.isEnrolled && !access.isPurchased && !access.isFree) {
    try {
      const subs = await getMySubscriptions(userToken);
      const normalSub = subs.find(
        (s) => s.status === "active" && s.type !== "b2b" && (s.courses ?? []).some((c) => c.id === courseId)
      );
      if (normalSub) {
        access.coverageType = "normal_sub";
        access.coveredBySubscription = true;
      } else {
        const b2bSub = subs.find(
          (s) => s.status === "active" && s.type === "b2b" && (s.courses ?? []).some((c) => c.id === courseId)
        );
        if (b2bSub) {
          access.coverageType = "b2b_sub";
          access.coveredBySubscription = true;
        } else {
          // Check user programs
          const progs = await getMyPrograms(userToken);
          for (const p of progs) {
            const details = await getProgramDetails(p.id, userToken);
            if (details?.content) {
              const extractIds = (items: typeof details.content): number[] => {
                const ids: number[] = [];
                for (const item of items) {
                  if (item.courseid) ids.push(Number(item.courseid));
                  if (item.children && Array.isArray(item.children)) ids.push(...extractIds(item.children));
                }
                return ids;
              };
              if (extractIds(details.content).includes(courseId)) {
                access.coverageType = "program";
                access.coveredBySubscription = true;
                break;
              }
            }
          }
        }
      }
    } catch {
      // best-effort
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
      token: contentToken,
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
