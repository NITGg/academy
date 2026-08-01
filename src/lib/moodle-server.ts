import "server-only";
import {
  MOODLE_BASE_URL,
  MOODLE_REST_ENDPOINT,
  MOODLE_ACADEMY_ENDPOINT,
} from "@/config/constants";

// ── Standard Moodle REST (server.php) ────────────────────────────────────────

interface MoodleRestOptions {
  functionName: string;
  useAdminToken?: boolean;
  token?: string;
  params?: Record<string, string | number | boolean | undefined>;
}

export async function callMoodleRest<T = unknown>({
  functionName,
  useAdminToken = false,
  token,
  params = {},
}: MoodleRestOptions): Promise<T> {
  const activeToken = useAdminToken ? process.env.MOODLE_ADMIN_TOKEN : token;

  if (!activeToken) {
    throw new Error("Missing Moodle token for server call");
  }

  const body = new URLSearchParams({
    wstoken: activeToken,
    moodlewsrestformat: "json",
    wsfunction: functionName,
  });

  for (const [key, val] of Object.entries(params)) {
    if (val !== undefined && val !== null) {
      body.append(key, String(val));
    }
  }

  const response = await fetch(MOODLE_REST_ENDPOINT, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body.toString(),
    cache: "no-store",
  });

  if (!response.ok) {
    const errorText = await response.text();
    const cleanError = extractCleanErrorMessage(errorText);
    throw new Error(
      `Moodle network error: ${response.status} ${cleanError || response.statusText}`,
    );
  }

  const data = await response.json();

  // Some Moodle WS functions (e.g. enrol_manual_enrol_users) return `null` on success.
  // Guard against null before probing exception/errorcode, otherwise a successful call
  // throws "Cannot read properties of null (reading 'exception')".
  if (data && (data.exception || data.errorcode)) {
    const errorDetails = data.debuginfo
      ? `${data.message || data.exception} (${data.debuginfo})`
      : (data.message ?? data.exception ?? "Moodle API exception");

    const isTokenError =
      data.errorcode === "invalidtoken" ||
      data.errorcode === "accessexception" ||
      String(data.message || "").toLowerCase().includes("invalid token") ||
      String(data.message || "").toLowerCase().includes("token not found");

    if (isTokenError) {
      console.warn(`[Moodle REST Token Invalid - ${functionName}]: ${errorDetails}`);
    } else {
      console.error(`[Moodle REST Error - ${functionName}]: ${errorDetails}`);
    }

    throw new Error(errorDetails);
  }

  return data as T;
}

function extractCleanErrorMessage(errorText: string): string {
  if (!errorText) return "";

  try {
    const parsed = JSON.parse(errorText);
    if (typeof parsed === "string") return parsed;
    if (parsed && typeof parsed === "object") {
      const msg = parsed.error || parsed.message || parsed.exception || parsed.debuginfo;
      if (msg) return String(msg);
    }
  } catch {
    // Not JSON
  }

  if (errorText.includes("<") && errorText.includes(">")) {
    const alertMatch = errorText.match(/class=['"][^'"]*alert-danger[^'"]*['"]>([\s\S]*?)<\/div>/i);
    if (alertMatch?.[1]) {
      const text = alertMatch[1].replace(/<[^>]+>/g, "").trim();
      if (text) return text;
    }

    const mainMatch = errorText.match(/id=['"]region-main['"]>([\s\S]*?)<\/div>/i);
    if (mainMatch?.[1]) {
      const text = mainMatch[1].replace(/<style[\s\S]*?<\/style>/gi, "").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
      if (text) return text;
    }

    const cleanText = errorText
      .replace(/<style[\s\S]*?<\/style>/gi, "")
      .replace(/<script[\s\S]*?<\/script>/gi, "")
      .replace(/<[^>]+>/g, " ")
      .replace(/\s+/g, " ")
      .trim();

    if (cleanText) {
      return cleanText.length > 200 ? cleanText.slice(0, 200) + "..." : cleanText;
    }
  }

  return errorText.trim().length > 200 ? errorText.trim().slice(0, 200) + "..." : errorText.trim();
}

async function _callAcademy<T>(
  functionName: string,
  params: Record<string, unknown>,
  token: string,
  lang: "ar" | "en",
  method: "GET" | "POST",
): Promise<T> {
  const url = new URL(MOODLE_ACADEMY_ENDPOINT);
  url.searchParams.set("function", functionName);
  url.searchParams.set("token", token);
  url.searchParams.set("alang", lang);

  let fetchInit: RequestInit;

  // The local_academy dispatcher reads function params from the query string
  // ($_GET) for BOTH read and write endpoints. For POST we still send the JSON
  // body (in case a handler reads it), but we always mirror scalar params into
  // the query string so the dispatcher can find them.
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && typeof v !== "object") {
      url.searchParams.set(k, String(v));
    }
  }

  if (method === "GET") {
    fetchInit = { method: "GET", cache: "no-store" };
  } else {
    fetchInit = {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(params),
      cache: "no-store",
    };
  }

  const response = await fetch(url.toString(), fetchInit);

  if (!response.ok) {
    const errorText = await response.text();
    const cleanError = extractCleanErrorMessage(errorText);
    const detailMsg = cleanError ? `: ${cleanError}` : "";

    if (cleanError.toLowerCase().includes("invalid token") || cleanError.toLowerCase().includes("token not found")) {
      console.warn(
        `[Academy API Token Invalid ${response.status}] Function: ${functionName}${detailMsg}`,
      );
    } else {
      console.error(
        `[Academy API HTTP Error ${response.status}] Function: ${functionName}${detailMsg}`,
      );
    }

    throw new Error(
      `Academy API HTTP error ${response.status}${detailMsg}`,
    );
  }

  const data = await response.json();

  if (data.status === "fail") {
    const errorMsg = String(data.error ?? "Academy API request failed");
    if (errorMsg.toLowerCase().includes("invalid token") || errorMsg.toLowerCase().includes("token not found")) {
      console.warn(
        `[Moodle Rejected Request - ${functionName}]:`,
        errorMsg,
      );
    } else {
      console.error(
        `[Moodle Rejected Request - ${functionName}]:`,
        errorMsg,
      );
    }
    throw new Error(errorMsg);
  }

  return data.data as T;
}

/** POST — for state-changing endpoints (request_lesson, etc.) */
export async function callAcademyApi<T = unknown>(
  functionName: string,
  body: Record<string, unknown> = {},
  token?: string,
  lang: "ar" | "en" = "ar",
): Promise<T> {
  const activeToken = token ?? process.env.MOODLE_ADMIN_TOKEN;
  if (!activeToken) throw new Error("Missing token for Academy API call");
  return _callAcademy<T>(functionName, body, activeToken, lang, "POST");
}

/**
 * GET — for PUBLIC read endpoints that require no authentication (e.g. get_frontpage_blocks).
 *
 * These endpoints are handled by the dispatcher BEFORE the token gate, so no token must be sent:
 * passing one triggers Moodle's global token authentication, which returns an HTML error page for a
 * token that isn't valid on this server (e.g. a prod token hitting a local instance).
 */
export async function callAcademyApiPublicGet<T = unknown>(
  functionName: string,
  params: Record<string, unknown> = {},
  lang: "ar" | "en" = "ar",
): Promise<T> {
  const url = new URL(MOODLE_ACADEMY_ENDPOINT);
  url.searchParams.set("function", functionName);
  url.searchParams.set("alang", lang);
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && typeof v !== "object") {
      url.searchParams.set(k, String(v));
    }
  }

  const response = await fetch(url.toString(), { method: "GET", cache: "no-store" });
  if (!response.ok) {
    throw new Error(
      `Academy API HTTP error: ${response.status} ${response.statusText}`,
    );
  }

  const data = await response.json();
  if (data.status === "fail") {
    throw new Error(data.error ?? "Academy API request failed");
  }
  return data.data as T;
}

/** GET — for read endpoints (get_teacher, get_lesson_settings, etc.) */
export async function callAcademyApiGet<T = unknown>(
  functionName: string,
  params: Record<string, unknown> = {},
  token?: string,
  lang: "ar" | "en" = "ar",
): Promise<T> {
  const activeToken = token ?? process.env.MOODLE_ADMIN_TOKEN;
  if (!activeToken) throw new Error("Missing token for Academy API GET call");
  return _callAcademy<T>(functionName, params, activeToken, lang, "GET");
}

// ── File upload (draft area) ──────────────────────────────────────────────────
// Uploads files to the user's private draft file area via /webservice/upload.php
// and returns the draft itemid, which is then handed to mod_assign_save_submission
// (or any WS function expecting a draft area). All files land in the SAME draft area
// by threading the generated itemid through subsequent uploads.

export async function uploadFilesToDraftArea(
  token: string,
  files: File[],
): Promise<number> {
  let itemid = 0;

  for (const file of files) {
    const form = new FormData();
    form.append("token", token);
    form.append("filearea", "draft");
    form.append("itemid", String(itemid));
    form.append("file_1", file, file.name);

    const res = await fetch(`${MOODLE_BASE_URL}/webservice/upload.php`, {
      method: "POST",
      body: form,
      cache: "no-store",
    });

    if (!res.ok) {
      throw new Error(`Upload failed: ${res.status} ${res.statusText}`);
    }

    const data = await res.json();

    // upload.php returns an array of file records on success, or an object with
    // `error`/`errorcode` on failure.
    if (data && (data.error || data.errorcode)) {
      throw new Error(data.error ?? "Moodle file upload rejected");
    }

    const first = Array.isArray(data) ? data[0] : data;
    if (first?.itemid) itemid = Number(first.itemid);
  }

  return itemid;
}

// ── Login token exchange ──────────────────────────────────────────────────────

export async function fetchMoodleToken(
  username: string,
  password: string,
): Promise<string> {
  const body = new URLSearchParams({
    username,
    password,
    service: "moodle_mobile_app",
  });

  const response = await fetch(`${MOODLE_BASE_URL}/login/token.php`, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body.toString(),
    cache: "no-store",
  });

  const data = await response.json();

  if (data.error || !data.token) {
    throw new Error(data.error ?? "Invalid credentials");
  }

  return data.token as string;
}
