"use client";

import { Film, PlayCircle } from "lucide-react";
import type { ActivityView } from "../types";

export function SessionRecordingViewer({
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
        <div className="flex size-11 items-center justify-center rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
          <Film className="size-6" />
        </div>
        <div>
          <h2 className="text-h3 font-bold text-foreground">{name}</h2>
          <p className="text-caption text-muted-foreground">
            {isArabic ? "تسجيل الجلسة المباشرة" : "Recorded Live Session"}
          </p>
        </div>
      </div>

      <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-purple-500/30 bg-purple-500/5 py-10 px-4 text-center">
        <div className="flex size-12 items-center justify-center rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400">
          <PlayCircle className="size-6" />
        </div>
        <div className="space-y-1">
          <h3 className="text-small font-bold text-foreground">
            {isArabic ? "مشاهدة التسجيل" : "Watch Session Recording"}
          </h3>
          <p className="text-caption text-muted-foreground max-w-sm">
            {isArabic
              ? "سيتوفر رابط تسجيل الجلسة هنا بعد انتهاء البث المباشر. يمكنك العودة لاحقاً لمشاهدة الجلسة."
              : "The session recording link will be available here after the live broadcast ends. You can return later to watch the session."}
          </p>
        </div>

        <div className="inline-flex items-center gap-1.5 rounded-full bg-purple-500/10 px-4 py-1.5 text-caption font-medium text-purple-700 dark:text-purple-300">
          <Film className="size-3.5" />
          <span>{isArabic ? "سيتوفر بعد انتهاء الجلسة" : "Available after session ends"}</span>
        </div>
      </div>
    </div>
  );
}
