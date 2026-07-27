"use client";

import { useState, useTransition } from "react";
import Image from "next/image";
import {
  Calendar,
  Check,
  X,
  CalendarClock,
  UserX,
  Undo2,
  Video,
  AlertCircle,
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

type BtnVariant = "primary" | "danger" | "outline";

function ActionButton({
  label,
  icon,
  variant = "outline",
  onClick,
  disabled,
}: {
  label: string;
  icon?: React.ReactNode;
  variant?: BtnVariant;
  onClick: () => void;
  disabled?: boolean;
}) {
  const variants: Record<BtnVariant, string> = {
    primary: "bg-primary text-primary-foreground hover:opacity-90",
    danger:
      "bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:border-red-900/40",
    outline:
      "border border-border bg-background text-foreground hover:bg-muted",
  };
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={cn(
        "inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-[12px] font-semibold transition disabled:opacity-50",
        variants[variant],
      )}
    >
      {icon}
      {label}
    </button>
  );
}

export function LessonCard({ lesson }: { lesson: Lesson }) {
  const [isPending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  // which inline time form is open: "suggest" (counter-offer) or "update" (reschedule)
  const [timeForm, setTimeForm] = useState<null | "suggest" | "update">(null);
  const [pickedUnix, setPickedUnix] = useState<number | null>(null);
  const [availability, setAvailability] = useState<TeacherAvailability | null>(
    null,
  );
  const [loadingAvail, setLoadingAvail] = useState(false);

  const run = (fn: () => Promise<LessonActionResult>, confirmMsg?: string) => {
    if (confirmMsg && !window.confirm(confirmMsg)) return;
    setError(null);
    startTransition(async () => {
      const res = await fn();
      if (!res.success) {
        setError(res.error ?? "حدث خطأ");
      } else {
        closeTimeForm();
      }
    });
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
      run(() =>
        studentRespondLesson(lesson.id, "suggest", {
          suggested_time: pickedUnix,
        }),
      );
    } else if (timeForm === "update") {
      run(() => requestTimeUpdate(lesson.id, pickedUnix));
    }
  };

  return (
    <div className="rounded-2xl border border-border bg-card p-4 shadow-sm">
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
              variant="primary"
              disabled={isPending}
              onClick={() => run(() => respondTimeUpdate(lesson.id, "accept"))}
            />
            <ActionButton
              label="رفض"
              icon={<X className="size-3.5" />}
              variant="outline"
              disabled={isPending}
              onClick={() => run(() => respondTimeUpdate(lesson.id, "reject"))}
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
              disabled={isPending || !pickedUnix}
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
          {lesson.can_join &&
            lesson.join_url &&
            lesson.jitsi_session?.available && (
              <a
                href={lesson.join_url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3 py-1.5 text-[12px] font-semibold text-primary-foreground transition hover:opacity-90"
              >
                <Video className="size-3.5" />
                انضمام للحصة
              </a>
            )}

          {actions.includes("accept") && (
            <ActionButton
              label="قبول الوقت"
              icon={<Check className="size-3.5" />}
              variant="primary"
              disabled={isPending}
              onClick={() =>
                run(() => studentRespondLesson(lesson.id, "accept"))
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
              disabled={isPending}
              onClick={() =>
                run(
                  () => studentRespondLesson(lesson.id, "reject"),
                  "هل تريد رفض الوقت المقترح وإنهاء الطلب؟",
                )
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
              disabled={isPending}
              onClick={() =>
                run(
                  () => reportTeacherAbsent(lesson.id),
                  "هل تريد الإبلاغ عن غياب المدرس؟ سيُعاد رصيد الحصة إليك.",
                )
              }
            />
          )}

          {actions.includes("cancel") && (
            <ActionButton
              label="إلغاء الحصة"
              icon={<X className="size-3.5" />}
              variant="danger"
              disabled={isPending}
              onClick={() =>
                run(
                  () => cancelConfirmedLesson(lesson.id),
                  "هل تريد إلغاء هذه الحصة المؤكدة؟",
                )
              }
            />
          )}

          {actions.includes("withdraw") && (
            <ActionButton
              label="سحب الطلب"
              icon={<Undo2 className="size-3.5" />}
              variant="danger"
              disabled={isPending}
              onClick={() =>
                run(
                  () => withdrawLessonRequest(lesson.id),
                  "هل تريد سحب هذا الطلب؟",
                )
              }
            />
          )}
        </div>
      )}
    </div>
  );
}
