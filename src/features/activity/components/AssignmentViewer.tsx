"use client";

import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  FileText,
  Loader2,
  Lock,
  MessageSquare,
  Paperclip,
  Send,
  X,
} from "lucide-react";
import { useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import type { AssignmentData } from "../types";
import { submitAssignment } from "../actions";

function fmtDate(ts: number, isArabic: boolean): string {
  const d = new Date(ts * 1000);
  return d.toLocaleString(isArabic ? "ar-EG" : "en-GB", {
    day: "numeric",
    month: "long",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function fmtBytes(bytes: number): string {
  if (!bytes) return "";
  const mb = bytes / (1024 * 1024);
  return mb >= 1 ? `${mb.toFixed(0)} MB` : `${Math.round(bytes / 1024)} KB`;
}

function stripHtml(html: string): string {
  if (typeof document === "undefined") return html.replace(/<[^>]*>/g, "");
  const el = document.createElement("div");
  el.innerHTML = html;
  return el.textContent ?? "";
}

function StatusBadge({
  status,
  isArabic,
}: {
  status: string | null;
  isArabic: boolean;
}) {
  if (!status || status === "new") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground">
        {isArabic ? "لم يُسلَّم" : "Not submitted"}
      </span>
    );
  }
  if (status === "draft") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400">
        {isArabic ? "مسودة" : "Draft"}
      </span>
    );
  }
  if (status === "submitted") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
        <CheckCircle2 className="size-3" />
        {isArabic ? "مُسلَّم" : "Submitted"}
      </span>
    );
  }
  return null;
}

function GradingBadge({
  status,
  isArabic,
}: {
  status: string | null;
  isArabic: boolean;
}) {
  if (!status || status === "notgraded") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground">
        {isArabic ? "لم يُصحَّح" : "Not graded"}
      </span>
    );
  }
  if (status === "graded") {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-medium text-primary">
        {isArabic ? "تم التصحيح" : "Graded"}
      </span>
    );
  }
  return null;
}

export function AssignmentViewer({
  assignment,
  courseId,
  isArabic,
}: {
  assignment: AssignmentData;
  courseId: number;
  isArabic: boolean;
}) {
  const {
    cmid,
    id,
    intro,
    duedate,
    grade,
    submissionStatus,
    gradingStatus,
    gradeForDisplay,
    gradedDate,
    feedbackComment,
    locked,
    canEdit,
    submissionDrafts,
    requireStatement,
    allowsOnlineText,
    allowsFile,
    maxFiles,
    maxBytes,
    acceptedTypes,
    submittedText,
    submittedFiles,
  } = assignment;

  const router = useRouter();
  const [text, setText] = useState(() => stripHtml(submittedText ?? ""));
  const [files, setFiles] = useState<File[]>([]);
  const [accepted, setAccepted] = useState(false);
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const now = Math.floor(Date.now() / 1000);
  const isPastDue = duedate > 0 && now > duedate;
  const canSubmit = canEdit && !locked;
  const isGraded = gradingStatus === "graded";

  const onPickFiles = (e: React.ChangeEvent<HTMLInputElement>) => {
    const picked = Array.from(e.target.files ?? []);
    if (!picked.length) return;
    setFiles((prev) => {
      const merged = [...prev, ...picked];
      return maxFiles > 0 ? merged.slice(0, maxFiles) : merged;
    });
    if (fileRef.current) fileRef.current.value = "";
  };

  const removeFile = (idx: number) =>
    setFiles((prev) => prev.filter((_, i) => i !== idx));

  const submit = () => {
    setError(null);

    if (!text.trim() && files.length === 0) {
      setError(
        isArabic
          ? "أضف نصاً أو ارفع ملفاً قبل التسليم"
          : "Add text or upload a file before submitting",
      );
      return;
    }
    if (requireStatement && !accepted) {
      setError(
        isArabic
          ? "يجب الموافقة على إقرار التسليم أولاً"
          : "You must accept the submission statement first",
      );
      return;
    }

    const fd = new FormData();
    fd.append("assignmentId", String(id));
    fd.append("courseId", String(courseId));
    fd.append("cmid", String(cmid));
    fd.append("text", text);
    fd.append("submissionDrafts", submissionDrafts ? "1" : "0");
    fd.append("requireStatement", requireStatement ? "1" : "0");
    fd.append("acceptStatement", accepted ? "1" : "0");
    for (const f of files) fd.append("files", f, f.name);

    startTransition(async () => {
      const res = await submitAssignment(fd);
      if (res.ok) {
        setDone(true);
        setFiles([]);
        router.refresh();
      } else {
        setError(
          res.error ??
            (isArabic ? "تعذّر تسليم الواجب" : "Could not submit assignment"),
        );
      }
    });
  };

  return (
    <div className="space-y-5">
      {/* Description */}
      {intro && (
        <div
          className="prose prose-sm max-w-none dark:prose-invert text-foreground leading-relaxed [&_img]:max-w-full [&_img]:rounded-lg"
          dangerouslySetInnerHTML={{ __html: intro }}
        />
      )}

      {/* Meta stats */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5 text-center">
          <div className="text-caption font-bold text-foreground">
            {grade > 0 ? String(grade) : "—"}
          </div>
          <div className="text-[11px] text-muted-foreground">
            {isArabic ? "الدرجة القصوى" : "Max grade"}
          </div>
        </div>
        <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5 text-center">
          <div className="flex justify-center">
            <StatusBadge status={submissionStatus} isArabic={isArabic} />
          </div>
          <div className="text-[11px] text-muted-foreground mt-1">
            {isArabic ? "حالة التسليم" : "Submission"}
          </div>
        </div>
        <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5 text-center">
          <div className="flex justify-center">
            <GradingBadge status={gradingStatus} isArabic={isArabic} />
          </div>
          <div className="text-[11px] text-muted-foreground mt-1">
            {isArabic ? "حالة التصحيح" : "Grading"}
          </div>
        </div>
      </div>

      {/* Due date */}
      {duedate > 0 && (
        <div
          className={`flex items-center gap-2 rounded-xl border px-4 py-3 text-caption ${
            isPastDue
              ? "border-red-400/40 bg-red-500/10 text-red-600 dark:text-red-400"
              : "border-amber-400/40 bg-amber-500/10 text-amber-700 dark:text-amber-400"
          }`}
        >
          <Clock className="size-4 shrink-0" />
          <span>
            {isArabic ? "الموعد النهائي: " : "Due: "}
            <span className="font-semibold">{fmtDate(duedate, isArabic)}</span>
            {isPastDue && (
              <span className="ms-2 opacity-80">
                {isArabic ? "(انتهى الموعد)" : "(past due)"}
              </span>
            )}
          </span>
        </div>
      )}

      {/* Grade panel */}
      {gradeForDisplay && (
        <div className="rounded-xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-4 space-y-1">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="size-5 text-emerald-500" />
            <span className="text-caption font-semibold text-foreground">
              {isArabic ? "درجتك" : "Your grade"}
            </span>
          </div>
          <p
            className="text-h1 font-bold text-emerald-600 dark:text-emerald-400 ps-7"
            dangerouslySetInnerHTML={{ __html: gradeForDisplay }}
          />
          {gradedDate && gradedDate > 0 && (
            <p className="text-[11px] text-muted-foreground ps-7">
              {isArabic ? "صُحِّح في: " : "Graded on: "}
              {fmtDate(gradedDate, isArabic)}
            </p>
          )}
        </div>
      )}

      {/* Teacher feedback comment */}
      {feedbackComment && feedbackComment.trim() && (
        <div className="rounded-xl border border-border bg-muted/20 px-4 py-3 space-y-1.5">
          <div className="flex items-center gap-2">
            <MessageSquare className="size-4 text-primary" />
            <span className="text-caption font-semibold text-foreground">
              {isArabic ? "ملاحظات المدرّس" : "Teacher feedback"}
            </span>
          </div>
          <div
            className="prose prose-sm max-w-none dark:prose-invert text-caption text-foreground leading-relaxed ps-6 [&_img]:max-w-full [&_img]:rounded-lg"
            dangerouslySetInnerHTML={{ __html: feedbackComment }}
          />
        </div>
      )}

      {/* Previously submitted files (read-only) */}
      {submittedFiles.length > 0 && (
        <div className="rounded-xl border border-border bg-muted/20 px-4 py-3 space-y-2">
          <p className="text-caption font-semibold text-foreground">
            {isArabic ? "ملفاتك المُسلَّمة" : "Your submitted files"}
          </p>
          <ul className="space-y-1">
            {submittedFiles.map((name, i) => (
              <li
                key={i}
                className="flex items-center gap-2 text-small text-muted-foreground"
              >
                <FileText className="size-4 shrink-0 text-primary/60" />
                {name}
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Locked notice */}
      {locked && (
        <div className="flex items-center gap-2 rounded-xl border border-border bg-muted/40 px-4 py-3 text-caption text-muted-foreground">
          <Lock className="size-4 shrink-0" />
          {isArabic
            ? "تم قفل هذا الواجب ولا يمكن التعديل عليه."
            : "This assignment is locked and cannot be edited."}
        </div>
      )}

      {/* ── Submission form ── */}
      {canSubmit && (
        <div className="rounded-2xl border border-border bg-card p-5 space-y-4">
          <h3 className="text-caption font-bold text-foreground">
            {submissionStatus === "submitted"
              ? isArabic
                ? "تعديل التسليم"
                : "Edit submission"
              : isArabic
                ? "تسليم الواجب"
                : "Submit assignment"}
          </h3>

          {allowsOnlineText && (
            <div className="space-y-1.5">
              <label className="text-small font-medium text-foreground">
                {isArabic ? "النص" : "Online text"}
              </label>
              <textarea
                value={text}
                onChange={(e) => setText(e.target.value)}
                rows={6}
                placeholder={
                  isArabic ? "اكتب إجابتك هنا..." : "Write your answer here..."
                }
                className="w-full rounded-xl border border-border bg-background px-4 py-3 text-caption text-foreground outline-none focus:border-primary transition-colors resize-y"
              />
            </div>
          )}

          {allowsFile && (
            <div className="space-y-2">
              <label className="text-small font-medium text-foreground">
                {isArabic ? "الملفات" : "File submissions"}
                <span className="ms-2 text-[11px] font-normal text-muted-foreground">
                  {maxFiles > 0 &&
                    (isArabic
                      ? `حتى ${maxFiles} ملف`
                      : `up to ${maxFiles} file${maxFiles > 1 ? "s" : ""}`)}
                  {maxBytes > 0 && ` · ${fmtBytes(maxBytes)}`}
                </span>
              </label>

              {files.length > 0 && (
                <ul className="space-y-1.5">
                  {files.map((f, i) => (
                    <li
                      key={i}
                      className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-small"
                    >
                      <span className="flex items-center gap-2 truncate text-foreground">
                        <FileText className="size-4 shrink-0 text-primary/60" />
                        <span className="truncate">{f.name}</span>
                        <span className="shrink-0 text-[11px] text-muted-foreground">
                          {fmtBytes(f.size)}
                        </span>
                      </span>
                      <button
                        type="button"
                        onClick={() => removeFile(i)}
                        className="shrink-0 rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-red-500 transition-colors"
                        aria-label={isArabic ? "إزالة" : "Remove"}
                      >
                        <X className="size-4" />
                      </button>
                    </li>
                  ))}
                </ul>
              )}

              {(maxFiles === 0 || files.length < maxFiles) && (
                <button
                  type="button"
                  onClick={() => fileRef.current?.click()}
                  className="inline-flex items-center gap-2 rounded-xl border border-dashed border-border px-4 py-2.5 text-small font-medium text-muted-foreground hover:border-primary hover:text-primary transition-colors"
                >
                  <Paperclip className="size-4" />
                  {isArabic ? "إضافة ملف" : "Add file"}
                </button>
              )}
              <input
                ref={fileRef}
                type="file"
                multiple={maxFiles !== 1}
                accept={acceptedTypes || undefined}
                onChange={onPickFiles}
                className="hidden"
              />
            </div>
          )}

          {requireStatement && (
            <label className="flex items-start gap-2 text-small text-foreground">
              <input
                type="checkbox"
                checked={accepted}
                onChange={(e) => setAccepted(e.target.checked)}
                className="mt-0.5 accent-[var(--color-primary)]"
              />
              <span className="text-muted-foreground">
                {isArabic
                  ? "هذا العمل من إنتاجي الخاص، باستثناء ما أشرت إلى استعانتي فيه بأعمال الآخرين."
                  : "This submission is my own work, except where I have acknowledged the use of the works of other people."}
              </span>
            </label>
          )}

          {error && (
            <div className="flex items-center gap-2 rounded-lg border border-red-400/40 bg-red-500/10 px-4 py-2.5 text-caption text-red-600 dark:text-red-400">
              <AlertTriangle className="size-4 shrink-0" />
              {error}
            </div>
          )}

          {done && !error && (
            <div className="flex items-center gap-2 rounded-lg border border-emerald-400/40 bg-emerald-500/10 px-4 py-2.5 text-caption text-emerald-700 dark:text-emerald-400">
              <CheckCircle2 className="size-4 shrink-0" />
              {isArabic ? "تم تسليم الواجب بنجاح" : "Assignment submitted successfully"}
            </div>
          )}

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={submit}
              disabled={pending}
              className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60"
            >
              {pending ? (
                <Loader2 className="size-5 animate-spin" />
              ) : (
                <Send className="size-5" />
              )}
              {submissionStatus === "submitted"
                ? isArabic
                  ? "حفظ التعديلات"
                  : "Save changes"
                : isArabic
                  ? "تسليم الواجب"
                  : "Submit assignment"}
            </button>
          </div>
        </div>
      )}

      {/* Submission not currently editable (locked / no attempt allowed). */}
      {!canSubmit && !isGraded && !locked && (
        <div className="flex items-center gap-2 rounded-xl border border-border bg-muted/30 px-4 py-3 text-caption text-muted-foreground">
          <Lock className="size-4 shrink-0" />
          {isArabic
            ? "لا يمكنك التسليم في الوقت الحالي."
            : "You cannot submit at this time."}
        </div>
      )}
    </div>
  );
}
