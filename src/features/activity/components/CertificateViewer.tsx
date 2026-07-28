"use client";

import { Award, Download, ExternalLink, Loader2, RefreshCw } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { getCertificateAutologinUrl } from "../actions";

/**
 * Opens a certificate (customcert / coursecertificate) activity INSIDE the site.
 */
export function CertificateViewer({
  cmid,
  name,
  isArabic,
}: {
  courseId?: number;
  cmid: number;
  name?: string;
  isArabic: boolean;
}) {
  const [src, setSrc] = useState<string | null>(null);
  const [downloadSrc, setDownloadSrc] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isLocalhost, setIsLocalhost] = useState(false);

  useEffect(() => {
    if (typeof window !== "undefined") {
      setIsLocalhost(
        window.location.hostname === "localhost" ||
          window.location.hostname === "127.0.0.1",
      );
    }
  }, []);

  const loadCertificate = useCallback(async () => {
    setLoading(true);
    setError(null);
    const res = await getCertificateAutologinUrl(cmid);
    setLoading(false);
    if (res.url) {
      const pdfUrl = res.url.includes("?")
        ? `${res.url}&downloadown=1`
        : `${res.url}?downloadown=1`;
      setSrc(pdfUrl);
      setDownloadSrc(pdfUrl);
    } else {
      setError(
        res.error ??
          (isArabic
            ? "تعذّر تحميل الشهادة. يرجى التأكد من استكمال جميع شروط الكورس."
            : "Could not load certificate. Please ensure all course requirements are met."),
      );
    }
  }, [cmid, isArabic]);

  useEffect(() => {
    void loadCertificate();
  }, [loadCertificate]);

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-border bg-card py-24 text-center">
        <Loader2 className="size-8 animate-spin text-primary" />
        <p className="text-caption text-muted-foreground font-medium">
          {isArabic ? "جاري تحميل الشهادة..." : "Loading certificate..."}
        </p>
      </div>
    );
  }

  if (src) {
    return (
      <div className="space-y-3">
        {isLocalhost ? (
          <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-primary/30 bg-primary/5 py-12 text-center p-6">
            <Award className="size-12 text-primary" />
            <div className="space-y-1">
              <h4 className="text-small font-bold text-foreground">
                {name || (isArabic ? "عرض الشهادة" : "View Certificate")}
              </h4>
              <p className="text-caption text-muted-foreground max-w-md">
                {isArabic
                  ? "على بيئة التطوير المحلية (Localhost)، يحظر المتصفح عرض إطار Moodle المباشر بسبب حماية (X-Frame-Options). على السيرفر (Production) تظهر الشهادة فوراً داخل الصفحة."
                  : "On Localhost, the browser blocks embedding Moodle iframe due to X-Frame-Options. On production, it renders directly inside the site."}
              </p>
            </div>
            <a
              href={src}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 font-bold text-primary-foreground hover:bg-primary/90 transition-colors text-xs"
            >
              <ExternalLink className="size-4" />
              {isArabic ? "فتح الشهادة في نافذة جديدة" : "Open certificate in new tab"}
            </a>
          </div>
        ) : (
          <iframe
            src={src}
            title={name || (isArabic ? "الشهادة" : "Certificate")}
            className="h-[75vh] w-full rounded-xl border border-border bg-white"
          />
        )}

        <div className="flex items-center justify-between pt-1">
          <div className="flex items-center gap-4">
            <a
              href={src}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 text-small text-muted-foreground hover:text-primary transition-colors"
            >
              <ExternalLink className="size-3.5" />
              {isArabic ? "فتح بملء الشاشة" : "Open full screen"}
            </a>
            <button
              type="button"
              onClick={loadCertificate}
              className="inline-flex items-center gap-1 text-small text-muted-foreground hover:text-primary transition-colors"
            >
              <RefreshCw className="size-3.5" />
              {isArabic ? "إعادة تحميل" : "Reload"}
            </button>
          </div>

          {downloadSrc && (
            <a
              href={downloadSrc}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-3.5 py-1.5 text-small font-medium text-primary hover:bg-primary/20 transition-colors"
            >
              <Download className="size-3.5" />
              {isArabic ? "تنزيل الشهادة" : "Download Certificate"}
            </a>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border py-14 text-center">
      <Award className="size-12 text-primary/60" />
      <p className="text-caption text-muted-foreground max-w-sm">{error}</p>
      <button
        type="button"
        onClick={loadCertificate}
        disabled={loading}
        className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60"
      >
        <RefreshCw className="size-4" />
        {isArabic ? "إعادة المحاولة" : "Try again"}
      </button>
    </div>
  );
}


