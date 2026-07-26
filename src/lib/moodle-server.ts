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
  const activeToken = useAdminToken
    ? process.env.MOODLE_ADMIN_TOKEN
    : token;

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
    throw new Error(`Moodle network error: ${response.status} ${response.statusText}`);
  }

  const data = await response.json();

  if (data.exception || data.errorcode) {
    throw new Error(data.message ?? data.exception ?? "Moodle API exception");
  }

  return data as T;
}

// ── local_academy dispatcher (api.php) ───────────────────────────────────────
// Endpoint: /local/academy/api.php?function=<fn>&token=<token>&alang=<ar|en>
// Response: { status: "success"|"fail", data?: T, error?: string }

export async function callAcademyApi<T = unknown>(
  functionName: string,
  body: Record<string, unknown> = {},
  token?: string,
  lang: "ar" | "en" = "ar",
): Promise<T> {
  const activeToken = token ?? process.env.MOODLE_ADMIN_TOKEN;
  if (!activeToken) throw new Error("Missing token for Academy API call");

  const url = new URL(`${MOODLE_BASE_URL}/local/academy/api.php`);
  url.searchParams.set("function", functionName);
  url.searchParams.set("token", activeToken);
  url.searchParams.set("alang", lang);

  const response = await fetch(url.toString(), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error(`Academy API HTTP error: ${response.status} ${response.statusText}`);
  }

  const data = await response.json();

  if (data.status === "fail") {
    throw new Error(data.error ?? "Academy API request failed");
  }

  return data.data as T;
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
