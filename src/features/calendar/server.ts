import "server-only";
import { getSessionFromCookie } from "@/lib/session";
import { callMoodleRest } from "@/lib/moodle-server";
import type { CalendarMonthView } from "./types";

export async function getCalendarMonth(
  year: number,
  month: number,
): Promise<CalendarMonthView | null> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return null;

  try {
    return await callMoodleRest<CalendarMonthView>({
      functionName: "core_calendar_get_calendar_monthly_view",
      token: session.wstoken,
      params: { year, month, courseid: 0, categoryid: 0 },
    });
  } catch (err) {
    console.error("[calendar] getCalendarMonth failed:", err);
    return null;
  }
}
