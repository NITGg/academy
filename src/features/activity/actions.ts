"use server";

import { revalidatePath } from "next/cache";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import {
  callMoodleRest,
  callAcademyApi,
  callAcademyApiGet,
  uploadFilesToDraftArea,
} from "@/lib/moodle-server";
import { parseMlang } from "@/lib/mlang";
import type {
  Quiz,
  QuizAttemptStart,
  QuizSubmitResult,
  QuizAttemptSummary,
  AnswerValue,
  AssignmentData,
  AssignmentSubmitResult,
  PageData,
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

// ── Assignment ────────────────────────────────────────────────────────────────

export async function getAssignmentData(
  cmid: number,
  courseId: number,
  instanceId: number,
): Promise<{ data?: AssignmentData; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const lang = langOf(await getLocale());
  try {
    // 1) Resolve the real assignment instance id from its cmid. getalltopics does
    //    not reliably expose `instance` for assign, so we source it here.
    const assignments = await callMoodleRest<{
      courses: Array<{
        assignments: Array<{
          id: number;
          cmid: number;
          intro: string;
          duedate: number;
          grade: number;
          submissiondrafts?: number;
          requiresubmissionstatement?: number;
          configs?: Array<{
            plugin: string;
            subtype: string;
            name: string;
            value: string;
          }>;
        }>;
      }>;
    }>({
      functionName: "mod_assign_get_assignments",
      token: session.wstoken,
      params: { "courseids[0]": courseId, includenotenrolledcourses: 1 },
    });

    const assignInfo = assignments.courses
      ?.flatMap((c) => c.assignments)
      .find((a) => a.cmid === cmid);

    // Prefer the instance id from get_assignments; fall back to the passed hint.
    const assignId = assignInfo?.id ?? instanceId;
    if (!assignId) {
      return {
        error: lang === "ar" ? "تعذّر تحديد الواجب" : "Could not identify assignment",
      };
    }

    // 2) Now fetch this student's submission status for that instance id.
    const statusRes = await Promise.allSettled([
      callMoodleRest<{
        lastattempt?: {
          submission?: {
            id: number;
            status: string;
            plugins?: Array<{
              type: string;
              editorfields?: Array<{ text: string }>;
              fileareas?: Array<{ files?: Array<{ filename: string }> }>;
            }>;
          };
          // gradingstatus lives on lastattempt, NOT on submission.
          gradingstatus?: string;
          locked?: boolean;
          graded?: boolean;
          canedit?: boolean;
          cansubmit?: boolean;
        };
        feedback?: {
          grade?: { grade: string; timemodified: number };
          gradefordisplay?: string;
          gradeddate?: number;
          plugins?: Array<{
            type: string;
            editorfields?: Array<{ text: string }>;
          }>;
        };
      }>({
        functionName: "mod_assign_get_submission_status",
        token: session.wstoken,
        params: { assignid: assignId },
      }),
    ]);

    const status =
      statusRes[0].status === "fulfilled" ? statusRes[0].value : undefined;

    const sub = status?.lastattempt?.submission;
    const feedback = status?.feedback;

    // Parse submission-plugin config (which types are enabled + file limits).
    const configs = assignInfo?.configs ?? [];
    const cfg = (plugin: string, name: string) =>
      configs.find(
        (c) =>
          c.plugin === plugin &&
          c.subtype === "assignsubmission" &&
          c.name === name,
      )?.value;

    // Read back any content already submitted (draft or final) for prefill.
    const plugins = sub?.plugins ?? [];
    const submittedText =
      plugins.find((p) => p.type === "onlinetext")?.editorfields?.[0]?.text ??
      "";
    const submittedFiles =
      plugins
        .find((p) => p.type === "file")
        ?.fileareas?.flatMap((fa) => fa.files ?? [])
        .map((f) => f.filename) ?? [];

    // Teacher's feedback comment (feedback `comments` plugin editor field).
    const feedbackComment =
      feedback?.plugins?.find((p) => p.type === "comments")?.editorfields?.[0]
        ?.text ?? "";

    // gradingstatus is on lastattempt; also treat a present grade date as graded
    // (covers marking-workflow states where the string isn't a plain "graded").
    const gradedDate = feedback?.gradeddate ?? null;
    let gradingStatus = status?.lastattempt?.gradingstatus ?? null;
    if (
      (!gradingStatus || gradingStatus === "notgraded") &&
      gradedDate &&
      gradedDate > 0
    ) {
      gradingStatus = "graded";
    }

    return {
      data: {
        id: assignId,
        cmid,
        intro: assignInfo?.intro ?? "",
        duedate: assignInfo?.duedate ?? 0,
        grade: assignInfo?.grade ?? 0,
        submissionStatus: sub?.status ?? null,
        gradingStatus,
        gradeForDisplay: feedback?.gradefordisplay ?? null,
        gradedDate,
        feedbackComment,
        canEdit: status?.lastattempt?.canedit ?? true,
        canSubmit: status?.lastattempt?.cansubmit ?? true,
        locked: status?.lastattempt?.locked ?? false,
        submissionDrafts: assignInfo?.submissiondrafts === 1,
        requireStatement: assignInfo?.requiresubmissionstatement === 1,
        allowsOnlineText: cfg("onlinetext", "enabled") === "1",
        allowsFile: cfg("file", "enabled") === "1",
        maxFiles: Number(cfg("file", "maxfilesubmissions") ?? 1),
        maxBytes: Number(cfg("file", "maxsubmissionsizebytes") ?? 0),
        acceptedTypes: cfg("file", "filetypeslist") ?? "",
        submittedText,
        submittedFiles,
      },
    };
  } catch (err) {
    return {
      error:
        err instanceof Error
          ? err.message
          : lang === "ar"
            ? "تعذّر تحميل الواجب"
            : "Could not load assignment",
    };
  }
}

/**
 * Submit an assignment fully in-site: saves online text and/or uploaded files, then
 * (when the assignment uses draft submissions) submits it for grading. Files are
 * pushed to the user's draft area first, then handed to mod_assign_save_submission.
 *
 * Called with a FormData so File objects can cross the server-action boundary.
 */
export async function submitAssignment(
  formData: FormData,
): Promise<AssignmentSubmitResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { ok: false, needsAuth: true };

  const lang = langOf(await getLocale());
  const err = (ar: string, en: string) => (lang === "ar" ? ar : en);

  const assignmentId = Number(formData.get("assignmentId"));
  const courseId = Number(formData.get("courseId"));
  const cmid = Number(formData.get("cmid"));
  const text = String(formData.get("text") ?? "").trim();
  const submissionDrafts = formData.get("submissionDrafts") === "1";
  const requireStatement = formData.get("requireStatement") === "1";
  const acceptStatement = formData.get("acceptStatement") === "1";
  const files = formData
    .getAll("files")
    .filter((f): f is File => f instanceof File && f.size > 0);

  if (!assignmentId) {
    return { ok: false, error: err("الواجب غير صالح", "Invalid assignment") };
  }
  if (!text && files.length === 0) {
    return {
      ok: false,
      error: err(
        "أضف نصاً أو ارفع ملفاً قبل التسليم",
        "Add text or upload a file before submitting",
      ),
    };
  }
  if (requireStatement && !acceptStatement) {
    return {
      ok: false,
      error: err(
        "يجب الموافقة على إقرار التسليم أولاً",
        "You must accept the submission statement first",
      ),
    };
  }

  try {
    const params: Record<string, string | number> = {
      assignmentid: assignmentId,
    };

    if (text) {
      params["plugindata[onlinetext_editor][text]"] = text;
      params["plugindata[onlinetext_editor][format]"] = 1; // HTML
      params["plugindata[onlinetext_editor][itemid]"] = 0;
    }

    if (files.length > 0) {
      const draftId = await uploadFilesToDraftArea(session.wstoken, files);
      params["plugindata[files_filemanager]"] = draftId;
    }

    // save_submission returns a warnings[] array — non-empty means a plugin rejected.
    const saveRes = await callMoodleRest<
      Array<{ item?: string; message?: string }>
    >({
      functionName: "mod_assign_save_submission",
      token: session.wstoken,
      params,
    });
    if (Array.isArray(saveRes) && saveRes.length > 0) {
      throw new Error(
        saveRes[0].message ?? err("تعذّر حفظ التسليم", "Could not save submission"),
      );
    }

    // Draft-based assignments need an explicit "submit for grading" step.
    if (submissionDrafts) {
      await callMoodleRest({
        functionName: "mod_assign_submit_for_grading",
        token: session.wstoken,
        params: {
          assignmentid: assignmentId,
          acceptsubmissionstatement: acceptStatement ? 1 : 0,
        },
      });
    }

    revalidatePath(`/courses/${courseId}`);
    revalidatePath(`/courses/${courseId}/activity/${cmid}`);
    return { ok: true, status: "submitted" };
  } catch (e) {
    return {
      ok: false,
      error:
        e instanceof Error
          ? e.message
          : err("تعذّر تسليم الواجب", "Could not submit assignment"),
    };
  }
}

/**
 * Get a fresh, self-authenticating URL that opens an assignment activity inside a
 * Moodle browser session (so the student can submit there). Uses only the generic
 * `open_activity_autologin` endpoint — never the certificate fallback.
 */
export async function getAssignmentAutologinUrl(
  cmid: number,
): Promise<{ url?: string; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const lang = langOf(await getLocale());
  try {
    const data = await callAcademyApi<{ url: string }>(
      "open_activity_autologin",
      { cmid },
      session.wstoken,
      lang,
    );
    if (data?.url) return { url: data.url };
  } catch (err) {
    const msg = err instanceof Error ? err.message : null;
    return {
      error:
        msg ?? (lang === "ar" ? "تعذّر فتح الواجب" : "Could not open assignment"),
    };
  }

  return {
    error: lang === "ar" ? "تعذّر فتح الواجب" : "Could not open assignment",
  };
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

// ── Page Activity ─────────────────────────────────────────────────────────────

export async function getPageData(
  cmid: number,
  courseId: number,
  instanceId?: number,
): Promise<{ data?: PageData; error?: string; needsAuth?: boolean }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };

  const lang = langOf(await getLocale());

  try {
    const res = await callMoodleRest<{
      pages?: Array<{
        id: number;
        coursemodule: number;
        course: number;
        name: string;
        intro?: string;
        content?: string;
        contentfiles?: Array<{
          filename: string;
          filepath: string;
          filesize: number;
          fileurl: string;
          mimetype?: string;
          timemodified: number;
        }>;
        introfiles?: Array<{
          filename: string;
          filepath: string;
          filesize: number;
          fileurl: string;
          mimetype?: string;
          timemodified: number;
        }>;
        timemodified?: number;
      }>;
    }>({
      functionName: "mod_page_get_pages_by_courses",
      token: session.wstoken,
      params: { "courseids[0]": courseId },
    });

    const pageInfo = res.pages?.find(
      (p) => p.coursemodule === cmid || (instanceId && p.id === instanceId),
    );

    if (!pageInfo) {
      return {
        error:
          lang === "ar"
            ? "تعذّر العثور على محتوى الصفحة"
            : "Could not find page activity content",
      };
    }

    const content = parseMlang(pageInfo.content ?? "", lang);
    const intro = parseMlang(pageInfo.intro ?? "", lang);
    const name = parseMlang(pageInfo.name ?? "", lang);

    return {
      data: {
        id: pageInfo.id,
        cmid: pageInfo.coursemodule,
        courseId: pageInfo.course,
        name,
        intro,
        content,
        contentfiles: pageInfo.contentfiles ?? [],
        introfiles: pageInfo.introfiles ?? [],
        timemodified: pageInfo.timemodified,
      },
    };
  } catch (err) {
    return {
      error:
        err instanceof Error
          ? err.message
          : lang === "ar"
          ? "تعذّر تحميل الصفحة"
          : "Failed to load page content",
    };
  }
}

