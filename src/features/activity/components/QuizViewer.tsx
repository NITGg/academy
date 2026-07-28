"use client";

import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  Loader2,
  Play,
  RotateCcw,
  XCircle,
} from "lucide-react";
import { useEffect, useState, useTransition } from "react";
import type {
  Quiz,
  QuizQuestion,
  QuizSubmitResult,
  QuizAttemptSummary,
  AnswerValue,
} from "../types";
import {
  startQuizAttempt,
  submitQuizAttempt,
  getMyQuizAttempts,
} from "../actions";

type Phase = "intro" | "attempt" | "result";

function fmtDuration(seconds: number, isArabic: boolean): string {
  if (!seconds) return isArabic ? "بدون حد زمني" : "No time limit";
  const m = Math.round(seconds / 60);
  return isArabic ? `${m} دقيقة` : `${m} min`;
}

export function QuizViewer({
  quiz,
  courseId,
  cmid,
  isArabic,
}: {
  quiz: Quiz;
  courseId: number;
  cmid: number;
  isArabic: boolean;
}) {
  const [phase, setPhase] = useState<Phase>("intro");
  const [attemptId, setAttemptId] = useState<number | null>(null);
  const [answers, setAnswers] = useState<Record<number, AnswerValue>>({});
  const [result, setResult] = useState<QuizSubmitResult | null>(null);
  const [attempts, setAttempts] = useState<QuizAttemptSummary[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [starting, startTransition] = useTransition();
  const [submitting, setSubmitting] = useState(false);

  // Load past attempts on the intro screen.
  useEffect(() => {
    if (phase !== "intro") return;
    let live = true;
    void getMyQuizAttempts(quiz.quizid).then((r) => {
      if (live && r.data) setAttempts(r.data);
    });
    return () => {
      live = false;
    };
  }, [phase, quiz.quizid]);

  const finishedCount = attempts.filter((a) => a.state === "finished").length;
  const attemptsLeft =
    quiz.attempts_allowed > 0 ? quiz.attempts_allowed - finishedCount : Infinity;
  const canStart = attemptsLeft > 0;

  const begin = () => {
    setError(null);
    startTransition(async () => {
      const res = await startQuizAttempt(quiz.quizid);
      if (res.data) {
        setAttemptId(res.data.attemptid);
        setAnswers({});
        setResult(null);
        setPhase("attempt");
      } else {
        setError(res.error ?? (isArabic ? "تعذّر بدء المحاولة" : "Could not start"));
      }
    });
  };

  const setAnswer = (q: QuizQuestion, value: AnswerValue) => {
    setAnswers((prev) => ({ ...prev, [q.questionid]: value }));
  };

  const answered = quiz.questions.filter(
    (q) => answers[q.questionid] !== undefined,
  ).length;

  const submit = async () => {
    if (attemptId == null) return;
    setSubmitting(true);
    setError(null);
    const payload = Object.entries(answers).map(([questionid, answer]) => ({
      questionid: Number(questionid),
      answer,
    }));
    const res = await submitQuizAttempt(attemptId, payload, courseId, cmid);
    setSubmitting(false);
    if (res.data) {
      setResult(res.data);
      setPhase("result");
    } else {
      setError(res.error ?? (isArabic ? "تعذّر التسليم" : "Could not submit"));
    }
  };

  // ── Intro ────────────────────────────────────────────────────────────────────
  if (phase === "intro") {
    return (
      <div className="space-y-5">
        {quiz.intro && (
          <p className="text-caption text-muted-foreground leading-relaxed">
            {quiz.intro}
          </p>
        )}

        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <Stat
            label={isArabic ? "الأسئلة" : "Questions"}
            value={String(quiz.questions.length)}
          />
          <Stat
            label={isArabic ? "الوقت" : "Time"}
            value={fmtDuration(quiz.timelimit, isArabic)}
          />
          <Stat
            label={isArabic ? "المحاولات المتاحة" : "Attempts"}
            value={
              quiz.attempts_allowed > 0
                ? `${Math.max(0, attemptsLeft)} / ${quiz.attempts_allowed}`
                : isArabic
                  ? "غير محدودة"
                  : "Unlimited"
            }
          />
        </div>

        {attempts.length > 0 && (
          <div className="space-y-2">
            <h3 className="text-caption font-semibold text-foreground">
              {isArabic ? "محاولاتك السابقة" : "Your previous attempts"}
            </h3>
            <div className="divide-y divide-border rounded-xl border border-border">
              {attempts.map((a) => (
                <div
                  key={a.attemptid}
                  className="flex items-center justify-between px-4 py-2.5 text-small"
                >
                  <span className="text-muted-foreground">
                    {isArabic ? "المحاولة" : "Attempt"} {a.attempt_number}
                  </span>
                  <span className="font-medium text-foreground">
                    {a.percent != null
                      ? `${a.percent}%`
                      : a.state === "finished"
                        ? "—"
                        : isArabic
                          ? "غير مكتملة"
                          : "incomplete"}
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}

        {error && <ErrorLine text={error} />}

        {canStart ? (
          <button
            type="button"
            onClick={begin}
            disabled={starting}
            className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60"
          >
            {starting ? (
              <Loader2 className="size-5 animate-spin" />
            ) : (
              <Play className="size-5" />
            )}
            {isArabic ? "ابدأ الاختبار" : "Start quiz"}
          </button>
        ) : (
          <div className="flex items-center gap-2 rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-caption text-amber-700 dark:text-amber-400">
            <AlertTriangle className="size-4 shrink-0" />
            {isArabic
              ? "لقد استنفدت جميع المحاولات المتاحة لهذا الاختبار"
              : "You have used all available attempts for this quiz"}
          </div>
        )}
      </div>
    );
  }

  // ── Attempt ──────────────────────────────────────────────────────────────────
  if (phase === "attempt") {
    return (
      <div className="space-y-5">
        <div className="sticky top-16 z-10 flex items-center justify-between rounded-xl border border-border bg-card/95 px-4 py-2.5 backdrop-blur">
          <span className="text-caption text-muted-foreground">
            {isArabic ? "تمت الإجابة على" : "Answered"} {answered} /{" "}
            {quiz.questions.length}
          </span>
          <Clock className="size-4 text-muted-foreground" />
        </div>

        {quiz.questions.map((q, idx) => (
          <QuestionCard
            key={q.questionid}
            q={q}
            index={idx + 1}
            value={answers[q.questionid]}
            onChange={(v) => setAnswer(q, v)}
            isArabic={isArabic}
          />
        ))}

        {error && <ErrorLine text={error} />}

        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={submit}
            disabled={submitting}
            className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60"
          >
            {submitting ? (
              <Loader2 className="size-5 animate-spin" />
            ) : (
              <CheckCircle2 className="size-5" />
            )}
            {isArabic ? "تسليم الاختبار" : "Submit quiz"}
          </button>
          {answered < quiz.questions.length && (
            <span className="text-caption text-muted-foreground">
              {isArabic
                ? `${quiz.questions.length - answered} سؤال بدون إجابة`
                : `${quiz.questions.length - answered} unanswered`}
            </span>
          )}
        </div>
      </div>
    );
  }

  // ── Result ───────────────────────────────────────────────────────────────────
  const passed = result ? result.percent >= 50 : false;
  return (
    <div className="space-y-6">
      <div
        className={`flex flex-col items-center gap-3 rounded-2xl border py-10 text-center ${
          passed
            ? "border-emerald-400/40 bg-emerald-500/10"
            : "border-amber-400/40 bg-amber-500/10"
        }`}
      >
        {passed ? (
          <CheckCircle2 className="size-14 text-emerald-500" />
        ) : (
          <AlertTriangle className="size-14 text-amber-500" />
        )}
        <div className="text-h1 font-bold text-foreground">{result?.percent}%</div>
        <div className="text-caption text-muted-foreground">
          {isArabic ? "النتيجة" : "Score"}: {result?.score} / {result?.max_score}
        </div>
      </div>

      <div className="space-y-2">
        {result?.results.map((r, i) => (
          <div
            key={r.questionid}
            className="flex items-center justify-between rounded-lg border border-border px-4 py-2.5 text-small"
          >
            <span className="flex items-center gap-2 text-foreground">
              {r.correct ? (
                <CheckCircle2 className="size-4 text-emerald-500" />
              ) : (
                <XCircle className="size-4 text-red-500" />
              )}
              {isArabic ? `السؤال ${i + 1}` : `Question ${i + 1}`}
            </span>
            <span className="text-muted-foreground">
              {r.mark} / {r.max_mark}
            </span>
          </div>
        ))}
      </div>

      <button
        type="button"
        onClick={() => {
          setPhase("intro");
          setResult(null);
          setAttemptId(null);
        }}
        className="inline-flex items-center gap-2 rounded-xl border border-border px-6 py-3 font-medium text-foreground hover:bg-muted/50 transition-colors"
      >
        <RotateCcw className="size-5" />
        {isArabic ? "العودة" : "Back"}
      </button>
    </div>
  );
}

// ── Sub-components ──────────────────────────────────────────────────────────────

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5 text-center">
      <div className="text-caption font-bold text-foreground">{value}</div>
      <div className="text-[11px] text-muted-foreground">{label}</div>
    </div>
  );
}

function ErrorLine({ text }: { text: string }) {
  return (
    <div className="flex items-center gap-2 rounded-lg border border-red-400/40 bg-red-500/10 px-4 py-2.5 text-caption text-red-600 dark:text-red-400">
      <AlertTriangle className="size-4 shrink-0" />
      {text}
    </div>
  );
}

function QuestionImages({ images }: { images: string[] }) {
  if (!images?.length) return null;
  return (
    <div className="flex flex-wrap gap-2">
      {images.map((src, i) => (
        // Question images are served by the token-authorised qfile.php endpoint.
        // eslint-disable-next-line @next/next/no-img-element
        <img
          key={i}
          src={src}
          alt=""
          className="max-h-56 rounded-lg border border-border object-contain"
        />
      ))}
    </div>
  );
}

function QuestionCard({
  q,
  index,
  value,
  onChange,
  isArabic,
}: {
  q: QuizQuestion;
  index: number;
  value: AnswerValue | undefined;
  onChange: (v: AnswerValue) => void;
  isArabic: boolean;
}) {
  const multi = q.type === "multichoice" && q.single === false;

  if (!q.supported) {
    return (
      <div className="rounded-xl border border-border bg-card p-4">
        <QuestionHead index={index} text={q.text} images={q.images} />
        <p className="mt-2 text-caption text-muted-foreground">
          {isArabic
            ? "نوع هذا السؤال غير مدعوم في العرض داخل الموقع."
            : "This question type is not supported inline."}
        </p>
      </div>
    );
  }

  const toggleMulti = (optId: number) => {
    const cur = Array.isArray(value) ? value : [];
    onChange(
      cur.includes(optId) ? cur.filter((x) => x !== optId) : [...cur, optId],
    );
  };

  return (
    <div className="rounded-xl border border-border bg-card p-4 space-y-3">
      <QuestionHead index={index} text={q.text} images={q.images} />
      <div className="space-y-2">
        {q.options?.map((opt) => {
          const selected = multi
            ? Array.isArray(value) && value.includes(opt.id)
            : value === opt.id;
          return (
            <label
              key={opt.id}
              className={`flex cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition-colors ${
                selected
                  ? "border-primary bg-primary/5"
                  : "border-border hover:bg-muted/40"
              }`}
            >
              <input
                type={multi ? "checkbox" : "radio"}
                name={`q-${q.questionid}`}
                checked={selected}
                onChange={() => (multi ? toggleMulti(opt.id) : onChange(opt.id))}
                className="mt-1 accent-[var(--color-primary)]"
              />
              <div className="flex-1 space-y-1">
                {opt.text && (
                  <span className="text-caption text-foreground">{opt.text}</span>
                )}
                <QuestionImages images={opt.images} />
              </div>
            </label>
          );
        })}
      </div>
    </div>
  );
}

function QuestionHead({
  index,
  text,
  images,
}: {
  index: number;
  text: string;
  images: string[];
}) {
  return (
    <div className="space-y-2">
      <div className="flex gap-2">
        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
          {index}
        </span>
        <p className="text-caption font-medium text-foreground leading-relaxed">
          {text}
        </p>
      </div>
      <QuestionImages images={images} />
    </div>
  );
}
