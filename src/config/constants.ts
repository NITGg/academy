export const MOODLE_BASE_URL =
  process.env.MOODLE_BASE_URL ?? "https://academy2026.nitg-eg.com/nextjs-frontend-student";

export const MOODLE_REST_ENDPOINT = `${MOODLE_BASE_URL}/webservice/rest/server.php`;
export const MOODLE_ACADEMY_ENDPOINT = `${MOODLE_BASE_URL}/local/academy/api.php`;

export const SESSION_COOKIE = "ea_session";
export const LOCALE_COOKIE = "locale";

// Pagination defaults
export const DEFAULT_PAGE_SIZE = 20;
