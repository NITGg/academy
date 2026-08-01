import { callMoodleRest, callAcademyApi, callAcademyApiPublicGet } from "@/lib/moodle-server";
import { getSessionFromCookie } from "@/lib/session";
import { enrichCoursesWithPricing, getMyCourses, type EnrolledCourse } from "@/features/courses/server";
import type { Course } from "@/features/courses/types";
import type { Teacher } from "@/features/teachers/types";
import type { CatalogueProgram, MyProgram } from "@/features/programs/types";
import type { AvailablePackage, MyPackage } from "@/features/packages/types";
import type {
  AvailableSubscription,
  MySubscription,
  B2BSubscription,
} from "@/features/subscriptions/types";

import { getTeachers } from "@/features/teachers/server";

/**
 * A custom-HTML block from the Moodle front page (`cocoon_custom_html`), rendered headless by
 * `local_academy/api.php?function=get_frontpage_blocks`. The `html` is self-contained (inline
 * styles + server-substituted stats/links), so it renders with the same appearance as the Moodle
 * site with no theme CSS required.
 */
export interface FrontpageBlock {
  id: number;
  title: string;
  region: string;
  weight: number;
  html: string;
}

export interface HomeDashboardData {
  frontpageBlocks: FrontpageBlock[];
  courses: Course[];
  myCourses: EnrolledCourse[];
  teachers: Teacher[];
  programs: CatalogueProgram[];
  packages: AvailablePackage[];
  subscriptions: AvailableSubscription[];
  myPackages: MyPackage[];
  mySubscriptions: MySubscription[];
  myB2bSubscriptions: B2BSubscription[];
  myPrograms: MyProgram[];
}

function moodlePublicUrl(url?: string): string | undefined {
  if (!url) return undefined;
  return url.replace("/webservice/pluginfile.php", "/pluginfile.php");
}

export async function getHomeDashboardData(userWstoken?: string): Promise<HomeDashboardData> {
  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  if (!adminToken) throw new Error("Admin token not configured");

  const session = await getSessionFromCookie();

  const [
    frontpageBlocksRes,
    coursesRes,
    teachersRes,
    programsRes,
    packagesRes,
    subscriptionsRes,
    myPackagesRes,
    mySubscriptionsRes,
    myB2bSubscriptionsRes,
    myProgramsRes,
    myCoursesRes,
  ] = await Promise.allSettled([
    // Public marketing blocks (hero + sections). Sent WITHOUT a token on purpose — the endpoint is
    // public, and passing a token that isn't valid on this Moodle instance triggers an HTML error.
    callAcademyApiPublicGet<{ blocks: FrontpageBlock[] }>("get_frontpage_blocks", {
      region: "fullwidth-top",
    }),
    callMoodleRest<{ courses?: Course[] } | Course[]>({
      functionName: "core_course_get_courses_by_field",
      token: adminToken,
    }),
    getTeachers({ page: 0 }),
    callAcademyApi<CatalogueProgram[]>(
      "get_catalogue_programs",
      {},
      userWstoken ?? adminToken,
    ),
    callAcademyApi<AvailablePackage[]>("get_available_packages"),
    callAcademyApi<AvailableSubscription[]>("get_available_subscriptions"),
    userWstoken
      ? callAcademyApi<MyPackage[]>("get_my_packages", {}, userWstoken)
      : Promise.resolve([]),
    userWstoken
      ? callAcademyApi<MySubscription[]>("get_my_subscriptions", {}, userWstoken)
      : Promise.resolve([]),
    userWstoken
      ? callAcademyApi<B2BSubscription[]>("get_my_b2b_subscriptions", {}, userWstoken)
      : Promise.resolve([]),
    userWstoken
      ? callAcademyApi<MyProgram[]>("get_my_programs", {}, userWstoken)
      : Promise.resolve([]),
    session?.wstoken && session.user?.id
      ? getMyCourses(session.wstoken, session.user.id)
      : Promise.resolve([] as EnrolledCourse[]),
  ]);

  // Courses — map overviewfiles → courseimage, then enrich with pricing/offers/access
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
    courses = await enrichCoursesWithPricing(courses, session?.wstoken);
  }

  const myCourses =
    myCoursesRes.status === "fulfilled" ? (myCoursesRes.value as EnrolledCourse[]) : [];

  // Teachers
  let teachers: Teacher[] = [];
  if (teachersRes.status === "fulfilled" && teachersRes.value) {
    const val = teachersRes.value as any;
    const list: any[] = Array.isArray(val)
      ? val
      : val?.teachers ?? val?.data?.teachers ?? [];
    teachers = list;
  }

  // Helper for array responses wrapped in { status, data: [...] } or direct array
  function extractList<T>(res: PromiseSettledResult<any>): T[] {
    if (res.status !== "fulfilled" || !res.value) return [];
    const v = res.value;
    if (Array.isArray(v)) return v;
    if (v && Array.isArray(v.data)) return v.data;
    return [];
  }

  const packages = extractList<AvailablePackage>(packagesRes);
  const subscriptions = extractList<AvailableSubscription>(subscriptionsRes);
  const programs = extractList<CatalogueProgram>(programsRes);
  const myPackages = extractList<MyPackage>(myPackagesRes);
  const mySubscriptions = extractList<MySubscription>(mySubscriptionsRes);
  const myB2bSubscriptions = extractList<B2BSubscription>(myB2bSubscriptionsRes);
  const myPrograms = extractList<MyProgram>(myProgramsRes);

  const frontpageBlocks: FrontpageBlock[] =
    frontpageBlocksRes.status === "fulfilled"
      ? (frontpageBlocksRes.value?.blocks ?? [])
      : [];

  return {
    frontpageBlocks,
    courses,
    myCourses,
    teachers,
    programs,
    packages,
    subscriptions,
    myPackages,
    mySubscriptions,
    myB2bSubscriptions,
    myPrograms,
  };
}

