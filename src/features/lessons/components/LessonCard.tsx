"use client";

import { useState, useTransition } from "react";
import Image from "next/image";
import Link from "next/link";
import {
  Calendar,
  Check,
  X,
  CalendarClock,
  UserX,
  Undo2,
  Video,
  AlertCircle,
  Loader2,
  AlertTriangle,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import type { Lesson } from "../types";
import {
  withdrawLessonRequest,
  studentRespondLesson,
  cancelConfirmedLesson,
  reportTeacherAbsent,
  requestTimeUpdate,
  respondTimeUpdate,
  type LessonActionResult,
} from "../actions";
import { SlotPicker } from "@/features/teachers/components/SlotPicker";
import {
  getTeacherAvailability,
  type TeacherAvailability,
} from "@/features/teachers/actions";

function formatDateTime(ts?: number): string {
  if (!ts) return "—";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const hours = String(d.getHours()).padStart(2, "0");
  const minutes = String(d.getMinutes()).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day} ${hours}:${minutes}`;
}

const STATUS_MAP: Record<string, { label: string; className: string }> = {
  pending: {
    label: "قيد الانتظار",
    className:
      "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
  },
  waiting_student: {
    label: "بانتظار ردّك",
    className:
      "bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400",
  },
  waiting_teacher: {
    label: "بانتظار المدرس",
    className:
      "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
  },
  confirmed: {
    label: "مؤكدة",
    className:
      "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
  },
  in_progress: { label: "جارية الآن", className: "bg-primary/10 text-primary" },
  completed: {
    label: "مكتملة",
    className:
      "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
  },
  rejected: {
    label: "مرفوضة",
    className: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400",
  },
  cancelled: { label: "ملغية", className: "bg-muted text-muted-foreground" },
  cancelled_teacher: {
    label: "ألغاها المدرس",
    className: "bg-muted text-muted-foreground",
  },
  student_absent: {
    label: "تغيّبت",
    className: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400",
  },
  teacher_absent: {
    label: "تغيّب المدرس",
    className: "bg-muted text-muted-foreground",
  },
};

function StatusBadge({ status }: { status: string }) {
  const s = STATUS_MAP[status] ?? {
    label: status,
    className: "bg-muted text-muted-foreground",
  };
  return (
    <span
      className={cn(
        "rounded-full px-2.5 py-0.5 text-[11px] font-semibold",
        s.className,
      )}
    >
      {s.label}
    </span>
  );
}

type BtnVariant = "primary" | "success" | "danger" | "outline";

function ActionButton({
  label,
  icon,
  variant = "outline",
  onClick,
  disabled,
  loading,
}: {
  label: string;
  icon?: React.ReactNode;
  variant?: BtnVariant;
  onClick: () => void;
  disabled?: boolean;
  loading?: boolean;
}) {
  const variants: Record<BtnVariant, string> = {
    primary:
      "bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm shadow-primary/20",
    success:
      "bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm shadow-emerald-600/20",
    danger:
      "bg-rose-50 text-rose-600 border border-rose-200/80 hover:bg-rose-100 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/40",
    outline:
      "border border-border/80 bg-card text-foreground hover:bg-muted hover:border-border shadow-xs",
  };
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled || loading}
      className={cn(
        "inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition-all duration-150 active:scale-[0.98] disabled:opacity-50 disabled:pointer-events-none cursor-pointer",
        variants[variant],
      )}
    >
      {loading ? <Loader2 className="size-3.5 animate-spin" /> : icon}
      <span>{label}</span>
    </button>
  );
}

// ── Confirm dialog ────────────────────────────────────────────────────────────

type DialogTone = "danger" | "warning";

interface ConfirmConfig {
  actionKey: string;
  fn: () => Promise<LessonActionResult>;
  tone: DialogTone;
  icon: React.ReactNode;
  title: string;
  body: string;
  note?: string;
  confirmLabel: string;
}

function LessonConfirmDialog({
  config,
  busy,
  onConfirm,
  onCancel,
}: {
  config: ConfirmConfig;
  busy: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  const isDanger = config.tone === "danger";
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-[2px]">
      <div
        className="w-full max-w-sm rounded-2xl border border-border bg-card shadow-2xl shadow-black/20 overflow-hidden"
        style={{ animation: "lessonModalIn 0.18s ease both" }}
      >
        {/* Header stripe */}
        <div className={cn(
          "flex items-center gap-3 px-5 py-4",
          isDanger ? "bg-rose-50 dark:bg-rose-950/30" : "bg-amber-50 dark:bg-amber-950/30"
        )}>
          <span className={cn(
            "flex size-9 shrink-0 items-center justify-center rounded-full",
            isDanger
              ? "bg-rose-100 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400"
              : "bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400"
          )}>
            {config.icon}
          </span>
          <h3 className={cn(
            "text-[15px] font-bold",
            isDanger ? "text-rose-700 dark:text-rose-300" : "text-amber-700 dark:text-amber-300"
          )}>
            {config.title}
          </h3>
        </div>

        {/* Body */}
        <div className="px-5 py-4 space-y-3">
          <p className="text-sm text-foreground leading-relaxed text-right">{config.body}</p>
          {config.note && (
            <div className={cn(
              "flex items-start gap-2 rounded-xl px-3 py-2.5 text-[12px] leading-relaxed",
              isDanger
                ? "bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400"
                : "bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400"
            )}>
              <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
              <span className="text-right">{config.note}</span>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
          <button
            type="button"
            onClick={onCancel}
            disabled={busy}
            className="rounded-xl border border-border bg-background px-4 py-2 text-xs font-semibold text-muted-foreground transition hover:bg-muted disabled:opacity-50"
          >
            إلغاء
          </button>
          <button
            type="button"
            onClick={onConfirm}
            disabled={busy}
            className={cn(
              "inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold text-white transition disabled:opacity-50",
              isDanger
                ? "bg-rose-600 hover:bg-rose-700 shadow-sm shadow-rose-600/30"
                : "bg-amber-500 hover:bg-amber-600 shadow-sm shadow-amber-500/30"
            )}
          >
            {busy && <Loader2 className="size-3.5 animate-spin" />}
            {config.confirmLabel}
          </button>
        </div>
      </div>
      <style>{`
        @keyframes lessonModalIn {
          from { opacity: 0; transform: scale(0.95) translateY(6px); }
          to   { opacity: 1; transform: scale(1)    translateY(0); }
        }
      `}</style>
    </div>
  );
}

// ── LessonCard ────────────────────────────────────────────────────────────────

export function LessonCard({ lesson }: { lesson: Lesson }) {
  const [isPending, startTransition] = useTransition();
  const [activeAction, setActiveAction] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pendingConfirm, setPendingConfirm] = useState<ConfirmConfig | null>(null);
  // which inline time form is open: "suggest" (counter-offer) or "update" (reschedule)
  const [timeForm, setTimeForm] = useState<null | "suggest" | "update">(null);
  const [pickedUnix, setPickedUnix] = useState<number | null>(null);
  const [availability, setAvailability] = useState<TeacherAvailability | null>(
    null,
  );
  const [loadingAvail, setLoadingAvail] = useState(false);

  const execute = (actionKey: string, fn: () => Promise<LessonActionResult>) => {
    setPendingConfirm(null);
    setError(null);
    setActiveAction(actionKey);
    startTransition(async () => {
      try {
        const res = await fn();
        if (!res.success) {
          setError(res.error ?? "حدث خطأ");
        } else {
          closeTimeForm();
        }
      } finally {
        setActiveAction(null);
      }
    });
  };

  // run without confirm dialog (for simple actions that don't need one)
  const run = (actionKey: string, fn: () => Promise<LessonActionResult>) => {
    execute(actionKey, fn);
  };

  // run with a styled confirm dialog instead of window.confirm
  const runWithConfirm = (config: ConfirmConfig) => {
    setPendingConfirm(config);
  };

  const closeTimeForm = () => {
    setTimeForm(null);
    setPickedUnix(null);
    setError(null);
  };

  // Toggle a time form open, lazily fetching the teacher's availability for the picker.
  const openTimeForm = (kind: "suggest" | "update") => {
    if (timeForm === kind) {
      closeTimeForm();
      return;
    }
    setError(null);
    setPickedUnix(null);
    setTimeForm(kind);
    if (!availability && lesson.teacherid) {
      setLoadingAvail(true);
      getTeacherAvailability(lesson.teacherid)
        .then((a) => setAvailability(a))
        .catch(() => setError("تعذّر تحميل المواعيد المتاحة"))
        .finally(() => setLoadingAvail(false));
    }
  };

  const actions = lesson.actions ?? [];
  const displayTime =
    lesson.confirmed_time ||
    lesson.effective_time ||
    lesson.requested_time ||
    0;
  const teacherInitial = (lesson.teacher_name ?? "م").charAt(0);

  // A pending time-update proposal from the teacher (respond_time_update flow)
  const pendingProposal = (lesson.proposals ?? []).find(
    (p) => p.status === "pending" && p.proposed_by === "teacher",
  );

  const submitTime = () => {
    if (!pickedUnix) {
      setError("يرجى اختيار موعد من التقويم");
      return;
    }
    if (timeForm === "suggest") {
      run("suggest", () =>
        studentRespondLesson(lesson.id, "suggest", {
          suggested_time: pickedUnix,
        }),
      );
    } else if (timeForm === "update") {
      run("update", () => requestTimeUpdate(lesson.id, pickedUnix));
    }
  };

  return (
    <div className="rounded-2xl border border-border bg-card p-4 shadow-sm space-y-3">
      {/* Header */}
      <div className="flex items-center gap-3">
        <div className="relative size-12 shrink-0 overflow-hidden rounded-full border border-border bg-muted">
          {lesson.teacher_photo ? (
            <Image
              src={lesson.teacher_photo}
              alt={lesson.teacher_name ?? ""}
              fill
              sizes="48px"
              className="object-cover"
            />
          ) : (
            <span className="flex h-full w-full items-center justify-center text-base font-bold text-primary">
              {teacherInitial}
            </span>
          )}
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-center justify-between gap-2">
            <StatusBadge status={lesson.status} />
            <span className="truncate text-caption font-bold text-foreground">
              {lesson.teacher_name ?? "—"}
            </span>
          </div>
          <p className="mt-0.5 text-end text-[11px] text-muted-foreground">
            المدرس · {lesson.subject}
          </p>
          {displayTime > 0 && (
            <div className="mt-1 flex items-center justify-end gap-1 text-[11px] text-muted-foreground">
              <Calendar className="size-3.5 shrink-0" />
              <span>{formatDateTime(displayTime)}</span>
            </div>
          )}
        </div>
      </div>

      {/* Note */}
      {lesson.note && (
        <p className="mt-3 rounded-xl bg-muted/50 px-3 py-2 text-end text-[11px] text-muted-foreground">
          {lesson.note}
        </p>
      )}

      {/* Reject / cancel reason from the teacher */}
      {(lesson.reject_reason || lesson.cancel_reason) && (
        <p className="mt-2 flex items-center justify-end gap-1 text-end text-[11px] text-red-600 dark:text-red-400">
          <AlertCircle className="size-3.5 shrink-0" />
          <span>{lesson.reject_reason || lesson.cancel_reason}</span>
        </p>
      )}

      {/* Teacher's counter-offer while waiting for the student to respond */}
      {lesson.status === "waiting_student" && lesson.suggested_time ? (
        <div className="mt-3 rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-end dark:border-violet-900/40 dark:bg-violet-900/20">
          <p className="text-[11px] font-semibold text-violet-700 dark:text-violet-300">
            اقترح المدرس وقتاً جديداً:
          </p>
          <p className="mt-0.5 text-[12px] font-bold text-violet-900 dark:text-violet-200">
            {formatDateTime(lesson.suggested_time)}
          </p>
        </div>
      ) : null}

      {/* Teacher's pending reschedule proposal on a confirmed lesson */}
      {pendingProposal && (
        <div className="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-end dark:border-blue-900/40 dark:bg-blue-900/20">
          <p className="text-[11px] font-semibold text-blue-700 dark:text-blue-300">
            طلب المدرس تعديل الموعد إلى:
          </p>
          <p className="mt-0.5 text-[12px] font-bold text-blue-900 dark:text-blue-200">
            {formatDateTime(pendingProposal.proposed_time)}
          </p>
          <div className="mt-2 flex justify-end gap-2">
            <ActionButton
              label="موافقة"
              icon={<Check className="size-3.5" />}
              variant="success"
              loading={isPending && activeAction === "accept_proposal"}
              onClick={() => run("accept_proposal", () => respondTimeUpdate(lesson.id, "accept"))}
            />
            <ActionButton
              label="رفض"
              icon={<X className="size-3.5" />}
              variant="danger"
              loading={isPending && activeAction === "reject_proposal"}
              onClick={() => run("reject_proposal", () => respondTimeUpdate(lesson.id, "reject"))}
            />
          </div>
        </div>
      )}

      {/* Error */}
      {error && (
        <p className="mt-2 rounded-lg bg-red-50 px-3 py-1.5 text-end text-[11px] text-red-600 dark:bg-red-900/20 dark:text-red-400">
          {error}
        </p>
      )}

      {/* Inline availability picker for suggest / reschedule */}
      {timeForm && (
        <div className="mt-3 flex flex-col gap-3 rounded-xl border border-border bg-muted/30 p-3">
          <label className="text-end text-[11px] font-medium text-muted-foreground">
            {timeForm === "suggest"
              ? "اختر وقتاً بديلاً من مواعيد المدرس"
              : "اختر موعداً جديداً من مواعيد المدرس"}
          </label>

          {loadingAvail ? (
            <p className="py-6 text-center text-[12px] text-muted-foreground">
              جارٍ تحميل المواعيد المتاحة…
            </p>
          ) : availability ? (
            <SlotPicker
              hours={availability.hours}
              busyTimes={availability.busyTimes}
              minBookingMinutes={availability.minBookingMinutes}
              onSelect={setPickedUnix}
            />
          ) : (
            <p className="py-4 text-center text-[12px] text-muted-foreground">
              تعذّر تحميل المواعيد المتاحة
            </p>
          )}

          <div className="flex justify-end gap-2">
            <ActionButton
              label="إرسال"
              variant="primary"
              loading={isPending && (activeAction === "suggest" || activeAction === "update")}
              disabled={!pickedUnix}
              onClick={submitTime}
            />
            <ActionButton
              label="إلغاء"
              variant="outline"
              disabled={isPending}
              onClick={closeTimeForm}
            />
          </div>
        </div>
      )}

      {/* Action bar (driven by lesson.actions[]) */}
      {actions.length > 0 && (
        <div className="mt-3 flex flex-wrap justify-end gap-2 border-t border-border pt-3">
          {lesson.can_join && lesson.jitsi_session?.available && (
            <Link
              href={`/lessons/${lesson.id}/room`}
              className="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-primary-foreground shadow-sm shadow-primary/20 transition-all hover:bg-primary/90 hover:shadow-md active:scale-[0.98]"
            >
              <Video className="size-3.5" />
              انضمام للحصة
            </Link>
          )}

          {actions.includes("accept") && (
            <ActionButton
              label="قبول الوقت"
              icon={<Check className="size-3.5" />}
              variant="success"
              loading={isPending && activeAction === "accept"}
              onClick={() =>
                run("accept", () => studentRespondLesson(lesson.id, "accept"))
              }
            />
          )}

          {actions.includes("suggest") && (
            <ActionButton
              label="اقتراح وقت آخر"
              icon={<CalendarClock className="size-3.5" />}
              variant="outline"
              disabled={isPending}
              onClick={() => openTimeForm("suggest")}
            />
          )}

          {actions.includes("reject") && (
            <ActionButton
              label="رفض الوقت"
              icon={<X className="size-3.5" />}
              variant="danger"
              loading={isPending && activeAction === "reject"}
              onClick={() =>
                runWithConfirm({
                  actionKey: "reject",
                  fn: () => studentRespondLesson(lesson.id, "reject"),
                  tone: "danger",
                  icon: <X className="size-4" />,
                  title: "رفض الوقت المقترح",
                  body: "هل تريد رفض الوقت المقترح من المدرس؟ سيتم إنهاء الطلب.",
                  confirmLabel: "رفض الوقت",
                })
              }
            />
          )}

          {actions.includes("request_time_update") && (
            <ActionButton
              label="تعديل الموعد"
              icon={<CalendarClock className="size-3.5" />}
              variant="outline"
              disabled={isPending}
              onClick={() => openTimeForm("update")}
            />
          )}

          {actions.includes("report_teacher_absent") && (
            <ActionButton
              label="الإبلاغ عن غياب المدرس"
              icon={<UserX className="size-3.5" />}
              variant="danger"
              loading={isPending && activeAction === "report_teacher_absent"}
              onClick={() =>
                runWithConfirm({
                  actionKey: "report_teacher_absent",
                  fn: () => reportTeacherAbsent(lesson.id),
                  tone: "warning",
                  icon: <UserX className="size-4" />,
                  title: "الإبلاغ عن غياب المدرس",
                  body: "هل تريد الإبلاغ عن غياب المدرس عن هذه الحصة؟",
                  note: "سيُعاد رصيد الحصة إلى محفظتك تلقائياً.",
                  confirmLabel: "إبلاغ عن الغياب",
                })
              }
            />
          )}

          {actions.includes("cancel") && (
            <ActionButton
              label="إلغاء الحصة"
              icon={<X className="size-3.5" />}
              variant="danger"
              loading={isPending && activeAction === "cancel"}
              onClick={() =>
                runWithConfirm({
                  actionKey: "cancel",
                  fn: () => cancelConfirmedLesson(lesson.id),
                  tone: "danger",
                  icon: <Trash2 className="size-4" />,
                  title: "إلغاء الحصة المؤكدة",
                  body: "هل تريد إلغاء هذه الحصة؟ لا يمكن التراجع عن هذا الإجراء.",
                  confirmLabel: "إلغاء الحصة",
                })
              }
            />
          )}

          {actions.includes("withdraw") && (
            <ActionButton
              label="سحب الطلب"
              icon={<Undo2 className="size-3.5" />}
              variant="danger"
              loading={isPending && activeAction === "withdraw"}
              onClick={() =>
                runWithConfirm({
                  actionKey: "withdraw",
                  fn: () => withdrawLessonRequest(lesson.id),
                  tone: "danger",
                  icon: <Undo2 className="size-4" />,
                  title: "سحب الطلب",
                  body: "هل تريد سحب هذا الطلب وإلغاء الحجز؟",
                  confirmLabel: "سحب الطلب",
                })
              }
            />
          )}
        </div>
      )}

      {/* Styled confirm dialog — replaces window.confirm() */}
      {pendingConfirm && (
        <LessonConfirmDialog
          config={pendingConfirm}
          busy={isPending}
          onConfirm={() => execute(pendingConfirm.actionKey, pendingConfirm.fn)}
          onCancel={() => setPendingConfirm(null)}
        />
      )}
    </div>
  );
}
