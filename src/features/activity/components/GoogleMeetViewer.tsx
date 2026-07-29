"use client";

import { ExternalLink, Video, VideoIcon } from "lucide-react";
import type { GoogleMeetData } from "../types";

export function GoogleMeetViewer({
  meetData,
  isArabic,
}: {
  meetData: GoogleMeetData;
  isArabic: boolean;
}) {
  const { name, meetUrl, moodleUrl } = meetData;
  const targetUrl = meetUrl || moodleUrl;

  // Extract meeting code if it's a meet.google.com link
  let meetingCode: string | null = null;
  if (meetUrl && meetUrl.includes("meet.google.com/")) {
    const parts = meetUrl.split("meet.google.com/");
    if (parts[1]) {
      meetingCode = parts[1].split("?")[0];
    }
  }

  return (
    <div className="rounded-2xl border border-border bg-card p-6 shadow-sm md:p-8 space-y-6">
      {/* Header */}
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

      {/* Main Action Banner */}
      <div className="flex flex-col items-center justify-center gap-5 rounded-2xl border border-dashed border-teal-500/30 bg-teal-500/5 py-10 px-6 text-center">
        <div className="flex size-14 items-center justify-center rounded-2xl bg-teal-500/10 text-teal-600 dark:text-teal-400 ring-8 ring-teal-500/5">
          <VideoIcon className="size-7" />
        </div>

        <div className="space-y-1.5 max-w-md">
          <h3 className="text-lead font-bold text-foreground">
            {isArabic ? "غرفة الاجتماع المباشر" : "Live Meeting Room"}
          </h3>
          <p className="text-caption text-muted-foreground">
            {isArabic
              ? "اضغط على الزر أدناه للانضمام إلى الجلسة المباشرة عبر Google Meet"
              : "Click the button below to join the live session via Google Meet"}
          </p>
        </div>

        {meetingCode && (
          <div className="inline-flex items-center gap-2 rounded-full border border-teal-500/20 bg-background/80 px-4 py-1.5 text-caption font-mono text-teal-700 dark:text-teal-300 backdrop-blur-sm">
            <span className="size-2 rounded-full bg-emerald-500 animate-ping" />
            <span>meet.google.com/{meetingCode}</span>
          </div>
        )}

        {/* Join Button */}
        <a
          href={targetUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-2.5 rounded-xl bg-teal-600 px-6 py-3 text-small font-semibold text-white shadow-md transition-all hover:bg-teal-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-teal-500/50 active:scale-95"
        >
          <span>{isArabic ? "الانضمام إلى اجتماع Google Meet" : "Join Google Meet Session"}</span>
          <ExternalLink className="size-4" />
        </a>
      </div>
    </div>
  );
}
