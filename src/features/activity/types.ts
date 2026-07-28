import type { CompletionRule } from "@/features/courses/types";

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
