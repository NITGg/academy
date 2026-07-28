"use client";

import { Award, Download, ExternalLink, RefreshCw } from "lucide-react";
import { useState } from "react";

/**
 * Opens a certificate (customcert / coursecertificate) activity INSIDE the site
 * cleanly as a PDF stream.
 */
export function CertificateViewer({
  courseId,
  cmid,
  name,
  isArabic,
}: {
  courseId?: number;
  cmid: number;
  name?: string;
  isArabic: boolean;
}) {
  const basePath = process.env.NEXT_PUBLIC_BASE_PATH || "";
  const baseSrc = courseId
    ? `${basePath}/api/activity-file?courseId=${courseId}&cmid=${cmid}`
    : `${basePath}/api/activity-file?cmid=${cmid}`;
  const pdfSrc = `${baseSrc}#toolbar=0&navpanes=0`;
  const downloadSrc = `${baseSrc}&download=1`;
  const [failed, setFailed] = useState(false);

  return (
    <div className="space-y-3">
      {failed ? (
        <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border py-14 text-center">
          <Award className="size-12 text-primary/60" />
          <p className="text-caption text-muted-foreground max-w-sm">
            {isArabic
              ? "تعذّر تحميل الشهادة. يرجى التأكد من استكمال جميع شروط الكورس."
              : "Could not load certificate. Please ensure all course requirements are met."}
          </p>
          <button
            type="button"
            onClick={() => setFailed(false)}
            className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
          >
            <RefreshCw className="size-4" />
            {isArabic ? "إعادة المحاولة" : "Try again"}
          </button>
        </div>
      ) : (
        <object
          data={pdfSrc}
          type="application/pdf"
          className="h-[80vh] w-full rounded-xl border border-border bg-muted/30"
          onError={() => setFailed(true)}
          aria-label={name || (isArabic ? "الشهادة" : "Certificate")}
        >
          <iframe
            src={pdfSrc}
            title={name || (isArabic ? "الشهادة" : "Certificate")}
            className="h-[80vh] w-full rounded-xl border border-border"
          />
        </object>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
        <div className="flex items-center gap-4">
          <a
            href={baseSrc}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 text-small text-muted-foreground hover:text-primary transition-colors"
          >
            <ExternalLink className="size-3.5" />
            {isArabic ? "فتح بملء الشاشة" : "Open full screen"}
          </a>
          <button
            type="button"
            onClick={() => setFailed(false)}
            className="inline-flex items-center gap-1 text-small text-muted-foreground hover:text-primary transition-colors"
          >
            <RefreshCw className="size-3.5" />
            {isArabic ? "إعادة تحميل" : "Reload"}
          </button>
        </div>

        <a
          href={downloadSrc}
          download
          className="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-3.5 py-1.5 text-small font-medium text-primary hover:bg-primary/20 transition-colors"
        >
          <Download className="size-3.5" />
          {isArabic ? "تنزيل الشهادة" : "Download Certificate"}
        </a>
      </div>
    </div>
  );
}

