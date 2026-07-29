"use client";

import { useState } from "react";
import { ExternalLink, Loader2, Radio, Video, VideoOff } from "lucide-react";
import type { ActivityView } from "../types";
import { getActivityAutologinUrl } from "../actions";
import { JitsiRoom } from "@/features/lessons/components/JitsiRoom";

export function JitsiActivityViewer({
  activity,
  isArabic,
}: {
  activity: ActivityView;
  isArabic: boolean;
}) {
  const { cmid, name, jitsiSession } = activity;
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleOpenJitsi = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getActivityAutologinUrl(cmid);
      if (res.url) {
        window.open(res.url, "_blank");
      } else {
        setError(
          res.error ??
            (isArabic ? "تعذّر الانضمام لغرفة Jitsi" : "Could not join Jitsi room"),
        );
      }
    } catch {
      setError(isArabic ? "حدث خطأ غير متوقع" : "Unexpected error occurred");
    } finally {
      setLoading(false);
    }
  };

  // If we have full inline Jitsi credentials (server_url + room), render JitsiRoom directly inline
  if (jitsiSession && jitsiSession.server_url && jitsiSession.room) {
    return (
      <div className="space-y-4">
        <div className="flex items-center gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm">
          <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            <Radio className="size-5 animate-pulse" />
          </div>
          <div>
            <h2 className="text-small font-bold text-foreground">{name}</h2>
            <p className="text-caption text-muted-foreground">
              {isArabic ? "بث مباشر تفاعلي عبر Jitsi" : "Interactive live session via Jitsi"}
            </p>
          </div>
        </div>

        <div className="h-[600px] overflow-hidden rounded-2xl border border-border shadow-md">
          <JitsiRoom session={jitsiSession} isArabic={isArabic} />
        </div>
      </div>
    );
  }

  // Fallback: Autologin Action Card
  return (
    <div className="rounded-2xl border border-border bg-card p-6 shadow-sm md:p-8 space-y-6">
      <div className="flex items-center gap-3 border-b border-border/60 pb-4">
        <div className="flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
          <Video className="size-6" />
        </div>
        <div>
          <h2 className="text-h3 font-bold text-foreground">{name}</h2>
          <p className="text-caption text-muted-foreground">
            {isArabic ? "جلسة بث مباشر Jitsi" : "Live Jitsi Meeting Session"}
          </p>
        </div>
      </div>

      <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-emerald-500/30 bg-emerald-500/5 py-10 px-4 text-center">
        <div className="flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
          <Radio className="size-6 animate-pulse" />
        </div>
        <div className="space-y-1">
          <h3 className="text-small font-bold text-foreground">
            {isArabic ? "غرفة البث المباشر جاهزة" : "Live Room Ready"}
          </h3>
          <p className="text-caption text-muted-foreground max-w-sm">
            {isArabic
              ? "اضغط على الزر أدناه للانضمام إلى البث المباشر عبر Jitsi"
              : "Click below to join the live session on Jitsi"}
          </p>
        </div>

        {error && (
          <div className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-caption text-red-600 dark:text-red-400">
            {error}
          </div>
        )}

        <button
          onClick={handleOpenJitsi}
          disabled={loading}
          className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-small font-semibold text-white shadow-md hover:bg-emerald-700 active:scale-[0.98] transition-all disabled:opacity-50"
        >
          {loading ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <ExternalLink className="size-4" />
          )}
          <span>{isArabic ? "الانضمام إلى البث المباشر" : "Join Live Meeting"}</span>
        </button>
      </div>
    </div>
  );
}
