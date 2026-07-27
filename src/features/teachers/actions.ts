"use server";

import { callAcademyApi } from "@/lib/moodle-server";
import { getSessionFromCookie } from "@/lib/session";
import { getTeacher, getLessonSettings } from "./server";
import type { TeacherHour } from "./types";

export interface TeacherAvailability {
  hours: TeacherHour[];
  busyTimes: [number, number][];
  minBookingMinutes: number;
}

/** Fetch a teacher's availability on demand (for the reschedule/suggest picker). */
export async function getTeacherAvailability(
  teacherId: number,
): Promise<TeacherAvailability | null> {
  const [teacher, settings] = await Promise.all([
    getTeacher(teacherId),
    getLessonSettings(),
  ]);
  if (!teacher) return null;
  return {
    hours: teacher.hours ?? [],
    busyTimes: teacher.busy_times ?? [],
    minBookingMinutes: settings.min_booking_minutes,
  };
}

export async function bookLessonAction(formData: {
  teacherid: number;
  subject: string;
  requested_time: number; // Unix timestamp in seconds
  note?: string;
}) {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) {
    return {
      success: false,
      error: "انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى للحجز.",
    };
  }

  const teacherId = Number(formData.teacherid);
  const requestedTime = Number(formData.requested_time);

  if (isNaN(teacherId) || isNaN(requestedTime)) {
    return { success: false, error: "بيانات الوقت أو المدرس غير صالحة" };
  }

  try {
    const response = await callAcademyApi(
      "request_lesson",
      {
        teacherid: teacherId,
        subject: String(formData.subject || "").trim(),
        requested_time: requestedTime,
        note: String(formData.note || "").trim(),
      },
      session.wstoken,
    );

    return { success: true, data: response };
  } catch (err) {
    console.error("bookLessonAction:", err);
    const message = err instanceof Error ? err.message : "فشل حجز الحصة";
    return { success: false, error: message };
  }
}
