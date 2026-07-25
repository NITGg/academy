import "server-only";
import { MOODLE_BASE_URL, MOODLE_REST_ENDPOINT, MOODLE_ACADEMY_ENDPOINT } from "@/config/constants";

interface MoodleCallOptions {
  functionName?: string;
  useAdminToken?: boolean;
  token?: string;
  params?: Record<string, string | number | boolean>;
}

export async function callMoodleRest<T = unknown>({
  functionName,
  useAdminToken = false,
  token,
  params = {},
}: MoodleCallOptions): Promise<T> {
  const activeToken = useAdminToken
    ? process.env.MOODLE_ADMIN_TOKEN
    : token;

  if (!activeToken) {
    throw new Error("Missing Moodle token for server call");
  }

  const searchParams = new URLSearchParams({
    wstoken: activeToken,
    moodlewsrestformat: "json",
    ...(functionName ? { wsfunction: functionName } : {}),
  });

  Object.entries(params).forEach(([key, val]) => {
    if (val !== undefined && val !== null) {
      searchParams.append(key, String(val));
    }
  });

  const response = await fetch(`${MOODLE_REST_ENDPOINT}?${searchParams.toString()}`, {
    method: "GET",
    headers: { "Content-Type": "application/json" },
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error(`Moodle network error: ${response.statusText}`);
  }

  const data = await response.json();

  if (data.exception || data.errorcode) {
    throw new Error(data.message || data.exception || "Moodle API exception");
  }

  return data as T;
}

export async function callAcademyApi<T = unknown>(
  action: string,
  body: Record<string, unknown>,
  token?: string
): Promise<T> {
  const activeToken = token || process.env.MOODLE_ADMIN_TOKEN;

  const response = await fetch(`${MOODLE_ACADEMY_ENDPOINT}?action=${action}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...(activeToken ? { Authorization: `Bearer ${activeToken}` } : {}),
    },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error(`Academy API HTTP error: ${response.statusText}`);
  }

  const data = await response.json();

  if (data.status === "fail") {
    throw new Error(data.error || "Academy API request failed");
  }

  return data.data as T;
}