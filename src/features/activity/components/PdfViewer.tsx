"use client";

import { Download, FileText, Maximize2 } from "lucide-react";
import { useState } from "react";

/**
 * Renders a PDF inline via the token-safe file proxy. No external tab: the file is
 * embedded in an <iframe>, with a download button (attachment) as a fallback for
 * browsers whose built-in PDF viewer is disabled.
 */
export function PdfViewer({
  courseId,
  cmid,
  name,
  isArabic,
}: {
  courseId: number;
  cmid: number;
  name: string;
  isArabic: boolean;
}) {
  const src = `/api/activity-file?courseId=${courseId}&cmid=${cmid}`;
  const downloadHref = `${src}&download=1`;
  const [failed, setFailed] = useState(false);

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-end gap-2">
        <a
          href={downloadHref}
          className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-small font-medium text-foreground hover:bg-muted/50 transition-colors"
        >
          <Download className="size-4" />
          {isArabic ? "تحميل" : "Download"}
        </a>
      </div>

      {failed ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-16 text-center">
          <FileText className="size-10 text-muted-foreground/30" />
          <p className="text-caption text-muted-foreground">
            {isArabic
              ? "تعذّر عرض الملف داخل المتصفح. استخدم زر التحميل."
              : "Could not display the file inline. Use the download button."}
          </p>
        </div>
      ) : (
        <object
          data={src}
          type="application/pdf"
          className="h-[75vh] w-full rounded-xl border border-border bg-muted/30"
          onError={() => setFailed(true)}
          aria-label={name}
        >
          {/* Fallback content if <object> can't render the PDF */}
          <iframe
            src={src}
            title={name}
            className="h-[75vh] w-full rounded-xl border border-border"
          />
        </object>
      )}

      <a
        href={src}
        target="_self"
        className="inline-flex items-center gap-1.5 text-small text-muted-foreground hover:text-primary transition-colors"
      >
        <Maximize2 className="size-3.5" />
        {isArabic ? "فتح بملء الشاشة" : "Open full screen"}
      </a>
    </div>
  );
}
