"use client";

import { Download } from "lucide-react";

/**
 * Streams a video inline through the token-safe file proxy (which forwards Range
 * requests so seeking works). No external tab, no direct token exposure.
 */
export function VideoViewer({
  courseId,
  cmid,
  mime,
  isArabic,
}: {
  courseId: number;
  cmid: number;
  mime?: string;
  isArabic: boolean;
}) {
  const src = `/api/activity-file?courseId=${courseId}&cmid=${cmid}`;
  const downloadHref = `${src}&download=1`;

  return (
    <div className="space-y-3">
      <video
        controls
        controlsList="nodownload"
        preload="metadata"
        className="w-full rounded-xl border border-border bg-black"
        playsInline
      >
        <source src={src} type={mime || "video/mp4"} />
        {isArabic
          ? "متصفحك لا يدعم تشغيل الفيديو."
          : "Your browser does not support the video tag."}
      </video>

      <div className="flex items-center justify-end">
        <a
          href={downloadHref}
          className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-small font-medium text-foreground hover:bg-muted/50 transition-colors"
        >
          <Download className="size-4" />
          {isArabic ? "تحميل" : "Download"}
        </a>
      </div>
    </div>
  );
}
