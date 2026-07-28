import "server-only";
import { MOODLE_BASE_URL } from "@/config/constants";
import { parseMlang } from "@/lib/mlang";
import { callAcademyApi } from "@/lib/moodle-server";
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
 * Resolves a certificate activity (customcert, coursecertificate, etc.) to a direct PDF Response stream
 * by fetching its autologin URL server-side and appending `downloadown=1` to the Moodle view URL.
 */
export async function resolveCertificateResponse(
  cmid: number,
  userToken: string,
  lang: "ar" | "en" = "ar",
): Promise<Response | null> {
  let autologinUrl: string | null = null;

  try {
    const data = await callAcademyApi<{ url: string }>(
      "open_certificate",
      { certificateid: cmid, cmid },
      userToken,
      lang,
    );
    if (data?.url) autologinUrl = data.url;
  } catch {
    /* fallback to open_activity_autologin */
  }

  if (!autologinUrl) {
    try {
      const data = await callAcademyApi<{ url: string }>(
        "open_activity_autologin",
        { cmid },
        userToken,
        lang,
      );
      if (data?.url) autologinUrl = data.url;
    } catch {
      /* ignore */
    }
  }

  if (!autologinUrl) return null;

  try {
    // 1. Hit autologin URL with manual redirect handling to capture MoodleSession cookie
    const res1 = await fetch(autologinUrl, {
      redirect: "manual",
      cache: "no-store",
    });

    const cookieList: string[] = [];
    if (typeof res1.headers.getSetCookie === "function") {
      for (const sc of res1.headers.getSetCookie()) {
        const cookie = sc.split(";")[0];
        if (cookie) cookieList.push(cookie);
      }
    } else {
      const sc = res1.headers.get("set-cookie");
      if (sc) cookieList.push(sc.split(";")[0]);
    }

    const location = res1.headers.get("location");
    const cookieHeader = cookieList.join("; ");

    if (location) {
      const targetUrlObj = new URL(location, autologinUrl);
      if (
        targetUrlObj.pathname.includes("customcert/view.php") ||
        targetUrlObj.pathname.includes("coursecertificate/view.php") ||
        targetUrlObj.pathname.includes("view.php")
      ) {
        targetUrlObj.searchParams.set("downloadown", "1");
      }

      const pdfRes = await fetch(targetUrlObj.toString(), {
        headers: cookieHeader ? { Cookie: cookieHeader } : {},
        cache: "no-store",
        redirect: "follow",
      });

      if (
        pdfRes.ok &&
        (pdfRes.headers.get("content-type")?.includes("pdf") ||
          pdfRes.headers.get("content-type")?.includes("octet-stream"))
      ) {
        return pdfRes;
      }
    }

    // 2. Direct fallback: append downloadown=1 to autologinUrl directly
    const directUrlObj = new URL(autologinUrl);
    directUrlObj.searchParams.set("downloadown", "1");
    const directRes = await fetch(directUrlObj.toString(), {
      cache: "no-store",
      redirect: "follow",
    });

    if (
      directRes.ok &&
      (directRes.headers.get("content-type")?.includes("pdf") ||
        directRes.headers.get("content-type")?.includes("octet-stream"))
    ) {
      return directRes;
    }
  } catch {
    /* ignore fetch errors */
  }

  return null;
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

