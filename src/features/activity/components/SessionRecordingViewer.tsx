"use client";

import { useState } from "react";
import { ExternalLink, Film, Loader2, Play, PlayCircle } from "lucide-react";
import type { ActivityView } from "../types";
import { getActivityAutologinUrl } from "../actions";

export function SessionRecordingViewer({
  activity,
  isArabic,
}: {
  activity: ActivityView;
  isArabic: boolean;
}) {
  const { cmid, name } = activity;
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleOpenRecording = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getActivityAutologinUrl(cmid);
      if (res.url) {
        window.open(res.url, "_blank");
      } else {
        setError(
          res.error ??
            (isArabic ? "تعذّر فتح تسجيل الجلسة" : "Could not open session recording"),
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
              ? "اضغط أدناه لمشاهدة الاستعانة والتسجيل المسجل للجلسة"
              : "Click below to watch the recorded lecture playback"}
          </p>
        </div>

        {error && (
          <div className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-caption text-red-600 dark:text-red-400">
            {error}
          </div>
        )}

        <button
          onClick={handleOpenRecording}
          disabled={loading}
          className="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-6 py-3 text-small font-semibold text-white shadow-md hover:bg-purple-700 active:scale-[0.98] transition-all disabled:opacity-50"
        >
          {loading ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <Play className="size-4 fill-current" />
          )}
          <span>{isArabic ? "مشاهدة تسجيل الجلسة" : "Watch Recording"}</span>
        </button>
      </div>
    </div>
  );
}
