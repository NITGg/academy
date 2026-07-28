"use client";

import { FileText, Maximize2 } from "lucide-react";
import { useState } from "react";

/**
 * Renders a PDF inline via the token-safe file proxy. Downloads are disabled.
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
  const basePath = process.env.NEXT_PUBLIC_BASE_PATH || "";
  const baseSrc = `${basePath}/api/activity-file?courseId=${courseId}&cmid=${cmid}`;
  const pdfSrc = `${baseSrc}#toolbar=0&navpanes=0`;
  const [failed, setFailed] = useState(false);

  return (
    <div className="space-y-3">
      {failed ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-16 text-center">
          <FileText className="size-10 text-muted-foreground/30" />
          <p className="text-caption text-muted-foreground">
            {isArabic
              ? "تعذّر عرض الملف داخل المتصفح."
              : "Could not display the file inline."}
          </p>
        </div>
      ) : (
        <object
          data={pdfSrc}
          type="application/pdf"
          className="h-[75vh] w-full rounded-xl border border-border bg-muted/30"
          onError={() => setFailed(true)}
          aria-label={name}
        >
          {/* Fallback content if <object> can't render the PDF */}
          <iframe
            src={pdfSrc}
            title={name}
            className="h-[75vh] w-full rounded-xl border border-border"
          />
        </object>
      )}

      <a
        href={baseSrc}
        target="_self"
        className="inline-flex items-center gap-1.5 text-small text-muted-foreground hover:text-primary transition-colors"
      >
        <Maximize2 className="size-3.5" />
        {isArabic ? "فتح بملء الشاشة" : "Open full screen"}
      </a>
    </div>
  );
}
