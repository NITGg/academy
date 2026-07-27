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
    throw new Error(
      `Moodle network error: ${response.status} ${response.statusText}`,
    );
  }

  const data = await response.json();

  // Some Moodle WS functions (e.g. enrol_manual_enrol_users) return `null` on success.
  // Guard against null before probing exception/errorcode, otherwise a successful call
  // throws "Cannot read properties of null (reading 'exception')".
  if (data && (data.exception || data.errorcode)) {
    throw new Error(data.message ?? data.exception ?? "Moodle API exception");
  }

  return data as T;
}

// ── local_academy dispatcher (api.php) ───────────────────────────────────────
// Endpoint: /local/academy/api.php?function=<fn>&token=<token>&alang=<ar|en>
// Response: { status: "success"|"fail", data?: T, error?: string }
//
// READ endpoints (get_*) expect params as URL query params (GET).
// WRITE endpoints (state-changing) expect params as JSON body (POST).

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
    console.error(
      `[Academy API HTTP Error ${response.status}] Function: ${functionName}`,
      errorText,
    );
    throw new Error(
      `Academy API HTTP error: ${response.status} ${response.statusText}`,
    );
  }

  const data = await response.json();

  if (data.status === "fail") {
    console.error(
      `[Moodle Rejected Request - ${functionName}]:`,
      data.error || data,
    );
    throw new Error(data.error ?? "Academy API request failed");
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
