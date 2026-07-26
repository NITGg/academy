"use client";

import { useState } from "react";
import Image from "next/image";
import { Video, Calendar, Clock, MinusCircle, Coins } from "lucide-react";
import { cn } from "@/lib/utils";
import type { Lesson, FlexTx } from "../types";

function formatDateTime(ts: number): string {
  if (!ts) return "—";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const hours = String(d.getHours()).padStart(2, "0");
  const minutes = String(d.getMinutes()).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day} ${hours}:${minutes}`;
}

const STATUS_MAP: Record<string, { label: string; className: string }> = {
  pending: { label: "قيد الانتظار", className: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
  confirmed: { label: "مؤكدة", className: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400" },
  completed: { label: "مكتملة", className: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
  rejected: { label: "مرفوضة", className: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" },
  cancelled: { label: "ملغية", className: "bg-muted text-muted-foreground" },
  in_progress: { label: "جارية", className: "bg-primary/10 text-primary" },
};

function StatusBadge({ status }: { status: string }) {
  const s = STATUS_MAP[status] ?? { label: status, className: "bg-muted text-muted-foreground" };
  return (
    <span className={cn("rounded-full px-2.5 py-0.5 text-[11px] font-semibold", s.className)}>
      {s.label}
    </span>
  );
}

// ── Lessons tab ──────────────────────────────────────────────────────────────

function LessonsTab({ lessons }: { lessons: Lesson[] }) {
  if (lessons.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Video className="size-8 opacity-30" />
        <p className="text-caption">لا توجد حصص محجوزة</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {lessons.map((lesson) => {
        const displayTime = lesson.confirmed_time || lesson.requested_time || 0;
        const teacherInitial = (lesson.teacher_name ?? "م").charAt(0);

        return (
          <div key={lesson.id} className="flex items-center gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm">
            {/* Teacher avatar */}
            <div className="relative size-12 shrink-0 overflow-hidden rounded-full border border-border bg-muted">
              {lesson.teacher_photo ? (
                <Image src={lesson.teacher_photo} alt={lesson.teacher_name ?? ""} fill sizes="48px" className="object-cover" />
              ) : (
                <span className="flex h-full w-full items-center justify-center text-base font-bold text-primary">
                  {teacherInitial}
                </span>
              )}
            </div>

            {/* Info */}
            <div className="min-w-0 flex-1">
              <div className="flex items-start justify-between gap-2">
                <StatusBadge status={lesson.status} />
                <span className="truncate text-caption font-bold text-foreground">
                  {lesson.teacher_name ?? "—"}
                </span>
              </div>
              <p className="mt-0.5 text-end text-[11px] text-muted-foreground">
                المدرس · {lesson.subject}
              </p>
              {displayTime > 0 && (
                <div className="mt-1.5 flex items-center justify-end gap-1 text-[11px] text-muted-foreground">
                  <Calendar className="size-3.5 shrink-0" />
                  <span>{formatDateTime(displayTime)}</span>
                </div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ── Flex History tab ─────────────────────────────────────────────────────────

const FLEX_TYPE_MAP: Record<string, { label: string; icon: React.ReactNode; amountClass: string; iconClass: string }> = {
  reserve: {
    label: "حجز",
    icon: <Clock className="size-4" />,
    amountClass: "text-amber-600 dark:text-amber-400",
    iconClass: "bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400",
  },
  return: {
    label: "إرجاع",
    icon: <Coins className="size-4" />,
    amountClass: "text-emerald-600 dark:text-emerald-400",
    iconClass: "bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400",
  },
  consume: {
    label: "استهلاك",
    icon: <MinusCircle className="size-4" />,
    amountClass: "text-red-600 dark:text-red-400",
    iconClass: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400",
  },
  purchase: {
    label: "شراء",
    icon: <Coins className="size-4" />,
    amountClass: "text-primary",
    iconClass: "bg-primary/10 text-primary",
  },
};

function FlexHistoryTab({ history }: { history: FlexTx[] }) {
  if (history.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Coins className="size-8 opacity-30" />
        <p className="text-caption">لا يوجد سجل رصيد</p>
      </div>
    );
  }

  return (
    <div className="space-y-2">
      {history.map((tx) => {
        const t = FLEX_TYPE_MAP[tx.type] ?? {
          label: tx.type,
          icon: <Coins className="size-4" />,
          amountClass: "text-foreground",
          iconClass: "bg-muted text-muted-foreground",
        };

        return (
          <div key={tx.id} className="flex items-center gap-3 rounded-2xl border border-border bg-card px-4 py-3 shadow-sm">
            {/* Type icon */}
            <div className={cn("flex size-10 shrink-0 items-center justify-center rounded-full", t.iconClass)}>
              {t.icon}
            </div>

            {/* Info */}
            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-2">
                <span className="text-[11px] text-muted-foreground">الرصيد: {tx.balance}</span>
                <div className="flex items-center gap-1.5">
                  <span className={cn("text-caption font-bold", t.amountClass)}>{t.label}</span>
                </div>
              </div>
              <div className="flex items-center justify-between gap-2 mt-0.5 text-[11px] text-muted-foreground">
                <span>{formatDateTime(tx.timecreated)}</span>
                <span className={cn("font-bold", t.amountClass)}>
                  {tx.amount > 0 ? `+${tx.amount}` : tx.amount}
                </span>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ── Page root ────────────────────────────────────────────────────────────────

const TABS = [
  { id: "lessons", label: "حصصي" },
  { id: "flex", label: "رصيد الحصص" },
] as const;

type Tab = (typeof TABS)[number]["id"];

interface LessonsPageClientProps {
  lessons: Lesson[];
  flexHistory: FlexTx[];
}

export function LessonsPageClient({ lessons, flexHistory }: LessonsPageClientProps) {
  const [activeTab, setActiveTab] = useState<Tab>("lessons");

  return (
    <div className="space-y-4">
      <div className="flex border-b border-border">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={cn(
              "flex-1 py-2.5 text-small font-medium transition-colors",
              activeTab === tab.id
                ? "border-b-2 border-primary text-primary"
                : "text-muted-foreground hover:text-foreground"
            )}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {activeTab === "lessons" && <LessonsTab lessons={lessons} />}
      {activeTab === "flex" && <FlexHistoryTab history={flexHistory} />}
    </div>
  );
}
