import type { CompletionRule } from "@/features/courses/types";
import type { JitsiSession } from "@/features/lessons/types";

/**
 * Metadata for a single activity, resolved from getalltopics.php with the current
 * user's token (so access is already enforced: an activity the user cannot see is
 * simply absent and never reaches this shape). Drives which in-site viewer renders.
 */
export interface ActivityView {
  /** Course-module id (cmid) — the id used everywhere to address an activity. */
  cmid: number;
  courseId: number;
  name: string;
  modname: string;
  /** Module instance id (quizid, resourceid, …) — needed for view-tracking pings. */
  instance?: number;
  /** MIME type of the attached file, when any (e.g. "application/pdf", "video/mp4"). */
  mime?: string;
  hasFile: boolean;
  jitsiSession?: JitsiSession | null;
  // ── Completion ──────────────────────────────────────────────────────────────
  /** 0 = no tracking, 1 = manual (student marks done), 2 = automatic (rule-based). */
  completion: number;
  /** 0 = incomplete, 1 = complete, 2 = complete-pass, 3 = complete-fail. */
  completionstate: number;
  hascompletion: boolean;
  /** Per-rule breakdown driving the "للقيام به: ..." lines. */
  completiondetails: CompletionRule[];
}

// ── Quiz models (mirror local_academy quiz_manager output) ─────────────────────

export interface QuizOption {
  id: number;
  text: string;
  images: string[];
  /** Present only for admin token — never sent to students. */
  correct?: boolean;
}

export interface QuizQuestion {
  slot: number;
  questionid: number;
  type: string; // "multichoice" | "truefalse" | ...
  text: string;
  images: string[];
  defaultmark: number;
  supported: boolean;
  /** multichoice only: false = multiple answers allowed. */
  single?: boolean;
  options?: QuizOption[];
}

export interface Quiz {
  quizid: number;
  cmid: number;
  courseid: number;
  name: string;
  intro: string;
  timelimit: number; // seconds, 0 = no limit
  attempts_allowed: number; // 0 = unlimited
  questions: QuizQuestion[];
}

export interface QuizAttemptStart {
  attemptid: number;
  quizid: number;
  attempt_number: number;
  timestart: number;
  timelimit: number;
  state: string;
}

export interface QuizResultRow {
  questionid: number;
  type: string;
  mark: number;
  max_mark: number;
  correct: boolean;
}

export interface QuizSubmitResult {
  attemptid: number;
  state: string;
  score: number;
  max_score: number;
  percent: number;
  results: QuizResultRow[];
}

export interface QuizAttemptSummary {
  attemptid: number;
  attempt_number: number;
  state: string;
  score: number | null;
  max_score: number;
  percent: number | null;
  timestart: number;
  timefinish: number;
}

/** One question's answer as the student submits it. */
export type AnswerValue = number | number[];

// ── Assignment models ──────────────────────────────────────────────────────────

export interface AssignmentData {
  id: number;
  cmid: number;
  intro: string;
  duedate: number;
  grade: number;
  submissionStatus: string | null;
  gradingStatus: string | null;
  gradeForDisplay: string | null;
  gradedDate: number | null;
  /** Teacher's feedback comment (HTML), from the feedback `comments` plugin. */
  feedbackComment: string;
  canEdit: boolean;
  canSubmit: boolean;
  locked: boolean;
  // ── Submission config ──
  /** Student must click a "submit for grading" button (draft → submitted). */
  submissionDrafts: boolean;
  /** Student must accept the submission statement before submitting. */
  requireStatement: boolean;
  allowsOnlineText: boolean;
  allowsFile: boolean;
  maxFiles: number;
  /** Max bytes per file, 0 = site default. */
  maxBytes: number;
  /** Comma-separated accepted extensions (e.g. ".pdf,.docx"), "" = any. */
  acceptedTypes: string;
  // ── Already-submitted content (for prefill / read-back) ──
  submittedText: string;
  submittedFiles: string[];
}

export interface AssignmentSubmitResult {
  ok: boolean;
  /** New submission status after saving ("submitted" | "draft"). */
  status?: string;
  error?: string;
  needsAuth?: boolean;
}

// ── Page models ────────────────────────────────────────────────────────────────

export interface PageFile {
  filename: string;
  filepath?: string;
  filesize: number;
  fileurl: string;
  mimetype?: string;
  timemodified?: number;
}

export interface PageData {
  id: number;
  cmid: number;
  courseId: number;
  name: string;
  intro?: string;
  content: string;
  contentfiles?: PageFile[];
  introfiles?: PageFile[];
  timemodified?: number;
}

// ── URL (Link) models ──────────────────────────────────────────────────────────

export interface UrlData {
  id: number;
  cmid: number;
  courseId: number;
  name: string;
  intro?: string;
  externalUrl: string;
  timemodified?: number;
}


