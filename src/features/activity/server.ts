import "server-only";
import { MOODLE_BASE_URL } from "@/config/constants";
import { parseMlang } from "@/lib/mlang";
import type { RawActivity, RawCourseTopics } from "@/features/courses/types";
import type { ActivityView } from "./types";

/**
 * Fetch the raw course content tree from getalltopics.php with a specific token.
 *
 * IMPORTANT: the `fileurl` fields in the returned activities embed whatever token
 * we pass here, so callers that will forward a fileurl to the browser must NOT use
 * this directly — go through the /api/activity-file proxy instead. This helper is
 * server-only.
 */
export async function fetchCourseTopics(
  courseId: number,
  token: string,
  lang: "ar" | "en" = "ar",
): Promise<RawCourseTopics | null> {
  try {
    const res = await fetch(
      `${MOODLE_BASE_URL}/local/multitopics/getalltopics.php?` +
        new URLSearchParams({
          courseid: String(courseId),
          wstoken: token,
          lang,
        }),
      { cache: "no-store" },
    );
    if (!res.ok) return null;
    return (await res.json()) as RawCourseTopics;
  } catch {
    return null;
  }
}

/** Walk a course tree and return the activity whose cmid matches, or null. */
export function findActivityByCmid(
  topics: RawCourseTopics,
  cmid: number,
): RawActivity | null {
  for (const parent of topics.parents ?? []) {
    for (const a of parent.activities ?? []) {
      if (parseInt(a.id, 10) === cmid) return a;
    }
    for (const topic of parent.topics ?? []) {
      for (const a of topic.activities ?? []) {
        if (parseInt(a.id, 10) === cmid) return a;
      }
    }
  }
  return null;
}

/**
 * Resolve an activity's downloadable file URL (WITH its Moodle token) for a given
 * user, enforcing visibility: it calls getalltopics with the USER's token, so an
 * activity the user cannot see (not enrolled, locked, restricted) is simply absent
 * from the tree and this returns null. The returned URL is token-bearing and must
 * only be used server-side (e.g. streamed by the proxy) — never sent to the browser.
 */
export async function resolveActivityFileUrl(
  courseId: number,
  cmid: number,
  userToken: string,
  lang: "ar" | "en" = "ar",
): Promise<{ url: string; mime?: string; filename?: string } | null> {
  const topics = await fetchCourseTopics(courseId, userToken, lang);
  if (!topics) return null;

  const activity = findActivityByCmid(topics, cmid);
  if (!activity || !activity.fileurl) return null;
  if (activity.uservisible === false) return null;

  // Derive a friendly filename from the URL path for the download header.
  let filename: string | undefined;
  try {
    const path = new URL(activity.fileurl).pathname;
    filename = decodeURIComponent(path.split("/").pop() ?? "") || undefined;
  } catch {
    /* ignore */
  }

  return { url: activity.fileurl, mime: activity.resourcetype, filename };
}

/**
 * Resolve one activity's viewer metadata (name, modname, instance, completion, file
 * presence) for the in-site activity page. Uses the USER's token so an activity the
 * user cannot access is absent → returns null (the page then 404s / gates). Never
 * returns the token-bearing fileurl — only a `hasFile` flag + MIME cross the boundary.
 */
export async function getActivityForView(
  courseId: number,
  cmid: number,
  userToken: string,
  lang: "ar" | "en" = "ar",
): Promise<ActivityView | null> {
  const topics = await fetchCourseTopics(courseId, userToken, lang);
  if (!topics) return null;

  const a = findActivityByCmid(topics, cmid);
  if (!a || a.uservisible === false) return null;

  return {
    cmid,
    courseId,
    name: parseMlang(a.name ?? "", lang),
    modname: a.modname,
    instance: a.instance ? parseInt(a.instance, 10) : undefined,
    mime: a.resourcetype,
    hasFile: Boolean(a.fileurl),
    completion: a.completion ?? 0,
    completionstate: a.completionstate ?? 0,
    hascompletion: a.hascompletion ?? false,
    completiondetails: a.completiondetails ?? [],
  };
}
