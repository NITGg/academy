"use client";

import { useState } from "react";
import { ExternalLink, Loader2, Video, VideoIcon } from "lucide-react";
import type { ActivityView } from "../types";
import { getActivityAutologinUrl } from "../actions";

export function GoogleMeetViewer({
  activity,
  isArabic,
}: {
  activity: ActivityView;
  isArabic: boolean;
}) {
  const { cmid, name } = activity;
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleOpenGoogleMeet = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getActivityAutologinUrl(cmid);
      if (res.url) {
        window.open(res.url, "_blank");
      } else {
        setError(
          res.error ??
            (isArabic ? "تعذّر الانضمام لاجتماع Google Meet" : "Could not join Google Meet session"),
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
        <div className="flex size-11 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400">
          <Video className="size-6" />
        </div>
        <div>
          <h2 className="text-h3 font-bold text-foreground">{name}</h2>
          <p className="text-caption text-muted-foreground">
            {isArabic ? "اجتماع Google Meet" : "Google Meet Meeting"}
          </p>
        </div>
      </div>

      <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-teal-500/30 bg-teal-500/5 py-10 px-4 text-center">
        <div className="flex size-12 items-center justify-center rounded-full bg-teal-500/10 text-teal-600 dark:text-teal-400">
          <VideoIcon className="size-6" />
        </div>
        <div className="space-y-1">
          <h3 className="text-small font-bold text-foreground">
            {isArabic ? "غرفة اجتماع Google Meet جاهزة" : "Google Meet Room Ready"}
          </h3>
          <p className="text-caption text-muted-foreground max-w-sm">
            {isArabic
              ? "اضغط أدناه للانضمام مباشرة إلى لقاء Google Meet الخاص بالدرس"
              : "Click below to enter the live Google Meet room for this lesson"}
          </p>
        </div>

        {error && (
          <div className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-caption text-red-600 dark:text-red-400">
            {error}
          </div>
        )}

        <button
          onClick={handleOpenGoogleMeet}
          disabled={loading}
          className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-6 py-3 text-small font-semibold text-white shadow-md hover:bg-teal-700 active:scale-[0.98] transition-all disabled:opacity-50"
        >
          {loading ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <ExternalLink className="size-4" />
          )}
          <span>{isArabic ? "الانضمام إلى اجتماع Google Meet" : "Join Google Meet"}</span>
        </button>
      </div>
    </div>
  );
}
