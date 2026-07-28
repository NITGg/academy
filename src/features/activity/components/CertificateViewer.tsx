"use client";

import { Award, ExternalLink, Loader2, RefreshCw } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { getCertificateAutologinUrl } from "../actions";

/**
 * Opens a certificate (customcert / coursecertificate) activity INSIDE the site.
 * Fetches an auto-login / view URL on demand and loads it inside an iframe.
 */
export function CertificateViewer({
  cmid,
  isArabic,
}: {
  cmid: number;
  isArabic: boolean;
}) {
  const [src, setSrc] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadCertificate = useCallback(async () => {
    setLoading(true);
    setError(null);
    const res = await getCertificateAutologinUrl(cmid);
    setLoading(false);
    if (res.url) {
      setSrc(res.url);
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
        <iframe
          src={src}
          title={isArabic ? "الشهادة" : "Certificate"}
          className="h-[80vh] w-full rounded-xl border border-border bg-white"
        />
        <div className="flex items-center gap-4">
          <a
            href={src}
            target="_self"
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
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border py-14 text-center">
      <Award className="size-12 text-primary/60" />
      <p className="text-caption text-muted-foreground max-w-sm">
        {error}
      </p>
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
