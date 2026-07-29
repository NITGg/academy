"use client";

import { CalendarCheck, UserCheck } from "lucide-react";
import type { ActivityView } from "../types";

export function AttendanceViewer({
  activity,
  isArabic,
}: {
  activity: ActivityView;
  isArabic: boolean;
}) {
  const { name } = activity;

  return (
    <div className="rounded-2xl border border-border bg-card p-6 shadow-sm md:p-8 space-y-6">
      <div className="flex items-center gap-3 border-b border-border/60 pb-4">
        <div className="flex size-11 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
          <UserCheck className="size-6" />
        </div>
        <div>
          <h2 className="text-h3 font-bold text-foreground">{name}</h2>
          <p className="text-caption text-muted-foreground">
            {isArabic ? "سجل الحضور والغياب" : "Attendance & Participation Record"}
          </p>
        </div>
      </div>

      <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-sky-500/30 bg-sky-500/5 py-10 px-4 text-center">
        <div className="flex size-12 items-center justify-center rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400">
          <CalendarCheck className="size-6" />
        </div>
        <div className="space-y-1">
          <h3 className="text-small font-bold text-foreground">
            {isArabic ? "تسجيل ومتابعة الحضور" : "Attendance Tracking"}
          </h3>
          <p className="text-caption text-muted-foreground max-w-sm">
            {isArabic
              ? "يتم تسجيل حضورك تلقائياً من قبل المعلم خلال الجلسات المباشرة. يمكنك مراجعة سجل حضورك هنا."
              : "Your attendance is recorded automatically by the instructor during live sessions. You can review your attendance record here."}
          </p>
        </div>

        <div className="inline-flex items-center gap-1.5 rounded-full bg-sky-500/10 px-4 py-1.5 text-caption font-medium text-sky-700 dark:text-sky-300">
          <CalendarCheck className="size-3.5" />
          <span>{isArabic ? "يُدار بواسطة المعلم" : "Managed by instructor"}</span>
        </div>
      </div>
    </div>
  );
}
