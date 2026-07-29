"use client";

import { useState } from "react";
import { CalendarCheck, CheckSquare, ExternalLink, Loader2, UserCheck } from "lucide-react";
import type { ActivityView } from "../types";
import { getActivityAutologinUrl } from "../actions";

export function AttendanceViewer({
  activity,
  isArabic,
}: {
  activity: ActivityView;
  isArabic: boolean;
}) {
  const { cmid, name } = activity;
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleOpenAttendance = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getActivityAutologinUrl(cmid);
      if (res.url) {
        window.open(res.url, "_blank");
      } else {
        setError(
          res.error ??
            (isArabic ? "تعذّر فتح سجل الحضور" : "Could not open attendance record"),
        );
      }
    } catch {
      setError(isArabic ? "حدث خطأ غير متوقع" : "Unexpected error occurred");
    } finally {
      setLoading(false);
    }
  };

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
              ? "اضغط أدناه لاستعراض جلسات الحضور الخاصة بك وتسجيل تواجدك"
              : "Click below to review your attendance sessions and record your presence"}
          </p>
        </div>

        {error && (
          <div className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-caption text-red-600 dark:text-red-400">
            {error}
          </div>
        )}

        <button
          onClick={handleOpenAttendance}
          disabled={loading}
          className="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-6 py-3 text-small font-semibold text-white shadow-md hover:bg-sky-700 active:scale-[0.98] transition-all disabled:opacity-50"
        >
          {loading ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <ExternalLink className="size-4" />
          )}
          <span>{isArabic ? "عرض سجل الحضور" : "View Attendance Record"}</span>
        </button>
      </div>
    </div>
  );
}
