"use server";

import { revalidatePath } from "next/cache";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import {
  callMoodleRest,
  callAcademyApi,
  callAcademyApiGet,
} from "@/lib/moodle-server";
import type {
  Quiz,
  QuizAttemptStart,
  QuizSubmitResult,
  QuizAttemptSummary,
  AnswerValue,
} from "./types";

function langOf(locale: string): "ar" | "en" {
  return locale === "ar" ? "ar" : "en";
}

// ── Completion ────────────────────────────────────────────────────────────────

/**
 * Mark a MANUAL-completion activity (completion === 1) as done / not done for the
 * current user. Uses the student token so it acts as themselves. Automatic-completion
 * activities ignore this — their state is rule-driven.
 */
export async function markActivityComplete(
  cmid: number,
  courseId: number,
  completed = true,
): Promise<{ ok: boolean; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { ok: false, needsAuth: true };

  try {
    await callMoodleRest({
      functionName: "core_completion_update_activity_completion_status_manually",
      token: session.wstoken,
      params: { cmid, completed: completed ? 1 : 0 },
    });
    revalidatePath(`/courses/${courseId}`);
    revalidatePath(`/courses/${courseId}/activity/${cmid}`);
    return { ok: true };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر تحديث حالة الإتمام";
    return { ok: false, error: msg };
  }
}

// ── Quiz ──────────────────────────────────────────────────────────────────────

/** Load a quiz with its questions (student view — never includes correct flags). */
export async function getQuiz(
  cmid: number,
): Promise<{ data?: Quiz; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const lang = langOf(await getLocale());
  try {
    const data = await callAcademyApiGet<Quiz>("get_quiz", { cmid }, session.wstoken, lang);
    return { data };
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر تحميل الاختبار" };
  }
}

/** List the current user's attempts on a quiz (for the attempts history panel). */
export async function getMyQuizAttempts(
  quizid: number,
): Promise<{ data?: QuizAttemptSummary[]; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const lang = langOf(await getLocale());
  try {
    const data = await callAcademyApiGet<QuizAttemptSummary[]>(
      "get_my_quiz_attempts",
      { quizid },
      session.wstoken,
      lang,
    );
    return { data };
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر تحميل المحاولات" };
  }
}

/** Start a new attempt on a quiz. */
export async function startQuizAttempt(
  quizid: number,
): Promise<{ data?: QuizAttemptStart; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const lang = langOf(await getLocale());
  try {
    const data = await callAcademyApi<QuizAttemptStart>(
      "start_quiz_attempt",
      { quizid },
      session.wstoken,
      lang,
    );
    return { data };
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر بدء المحاولة" };
  }
}

/**
 * Submit all answers and finish the attempt in one shot. Answers are JSON-encoded
 * into a scalar string so the local_academy dispatcher (which reads params from the
 * query string) receives them — see moodle-server _callAcademy.
 */
export async function submitQuizAttempt(
  attemptid: number,
  answers: { questionid: number; answer: AnswerValue }[],
  courseId: number,
  cmid: number,
): Promise<{ data?: QuizSubmitResult; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const lang = langOf(await getLocale());
  try {
    const data = await callAcademyApi<QuizSubmitResult>(
      "submit_quiz_attempt",
      { attemptid, answers: JSON.stringify(answers) },
      session.wstoken,
      lang,
    );
    // Grade may satisfy an automatic-completion rule → refresh the course + activity.
    revalidatePath(`/courses/${courseId}`);
    revalidatePath(`/courses/${courseId}/activity/${cmid}`);
    return { data };
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر تسليم الاختبار" };
  }
}

// ── Certificate (customcert) ───────────────────────────────────────────────────

/**
 * Get a fresh, self-authenticating URL that opens a certificate activity inside a
 * browser session, addressed by its cmid. Requires the `open_activity_autologin`
 * endpoint in local_academy (see docs) — until it is deployed this returns an error
 * the UI surfaces gracefully.
 */
export async function getCertificateAutologinUrl(
  cmid: number,
): Promise<{ url?: string; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const lang = langOf(await getLocale());

  // 1. Try open_activity_autologin
  try {
    const data = await callAcademyApi<{ url: string }>(
      "open_activity_autologin",
      { cmid },
      session.wstoken,
      lang,
    );
    if (data?.url) return { url: data.url };
  } catch {
    /* try fallback */
  }

  // 2. Try open_certificate
  try {
    const data = await callAcademyApi<{ url: string }>(
      "open_certificate",
      { certificateid: cmid, cmid },
      session.wstoken,
      lang,
    );
    if (data?.url) return { url: data.url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : "تعذّر فتح الشهادة";
    return { error: msg };
  }

  return {
    error: lang === "ar" ? "تعذّر فتح الشهادة" : "Could not open certificate",
  };
}
