"use server";

import { revalidatePath } from "next/cache";
import { getLocale } from "next-intl/server";
import { callAcademyApi } from "@/lib/moodle-server";
import { getSessionFromCookie } from "@/lib/session";

export interface LessonActionResult {
  success: boolean;
  error?: string;
}

const SESSION_EXPIRED = "انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى.";

/**
 * Shared helper: resolve the student's token, POST a state-changing lesson call,
 * and revalidate the lessons page so the UI re-renders with fresh actions/status.
 */
async function callLessonAction(
  functionName: string,
  params: Record<string, unknown>,
): Promise<LessonActionResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) {
    return { success: false, error: SESSION_EXPIRED };
  }

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  try {
    await callAcademyApi(functionName, params, session.wstoken, lang);
    revalidatePath("/lessons");
    return { success: true };
  } catch (err) {
    console.error(`${functionName}:`, err);
    const message = err instanceof Error ? err.message : "تعذّر تنفيذ العملية";
    return { success: false, error: message };
  }
}

/** Withdraw a still-pending request (pending / waiting_student / waiting_teacher). */
export async function withdrawLessonRequest(
  lessonid: number,
  reason?: string,
): Promise<LessonActionResult> {
  return callLessonAction("cancel_lesson_request", {
    lessonid,
    reason: reason?.trim() ?? "",
  });
}

/** Respond to a teacher's counter-offer while status is waiting_student. */
export async function studentRespondLesson(
  lessonid: number,
  action: "accept" | "reject" | "suggest",
  opts?: { suggested_time?: number; reject_reason?: string },
): Promise<LessonActionResult> {
  if (action === "suggest" && !opts?.suggested_time) {
    return { success: false, error: "يرجى تحديد الوقت المقترح" };
  }
  return callLessonAction("student_respond_lesson", {
    lessonid,
    action,
    suggested_time: opts?.suggested_time,
    reject_reason: opts?.reject_reason?.trim(),
  });
}

/** Cancel a confirmed lesson (early = Flex returned, late = Flex consumed). */
export async function cancelConfirmedLesson(
  lessonid: number,
  reason?: string,
): Promise<LessonActionResult> {
  return callLessonAction("cancel_lesson_student", {
    lessonid,
    reason: reason?.trim() ?? "",
  });
}

/** Report the teacher as a no-show (confirmed/in_progress, past the grace window). */
export async function reportTeacherAbsent(
  lessonid: number,
): Promise<LessonActionResult> {
  return callLessonAction("report_teacher_absent", { lessonid });
}

/** Propose a new time for a confirmed lesson (before the update deadline). */
export async function requestTimeUpdate(
  lessonid: number,
  proposed_time: number,
): Promise<LessonActionResult> {
  if (!proposed_time) {
    return { success: false, error: "يرجى تحديد الوقت المقترح" };
  }
  return callLessonAction("request_time_update", { lessonid, proposed_time });
}

/** Accept or reject the other party's pending time-update proposal. */
export async function respondTimeUpdate(
  lessonid: number,
  action: "accept" | "reject",
): Promise<LessonActionResult> {
  return callLessonAction("respond_time_update", { lessonid, action });
}
