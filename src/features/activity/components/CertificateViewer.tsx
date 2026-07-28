"use client";

import { Award, ExternalLink, Loader2 } from "lucide-react";
import { useState } from "react";
import { getCertificateAutologinUrl } from "../actions";

/**
 * Opens a certificate (customcert) activity INSIDE the site. A certificate is a real
 * Moodle web page that needs a logged-in browser session, so we fetch a fresh,
 * single-use auto-login URL on demand and load it in an iframe — no external tab.
 *
 * Requires the `open_activity_autologin` endpoint in local_academy. Until it is
 * deployed the button surfaces the backend error instead of silently failing.
 */
export function CertificateViewer({
  cmid,
  isArabic,
}: {
  cmid: number;
  isArabic: boolean;
}) {
  const [src, setSrc] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const open = async () => {
    setLoading(true);
    setError(null);
    const res = await getCertificateAutologinUrl(cmid);
    setLoading(false);
    if (res.url) {
      setSrc(res.url);
    } else {
      setError(
        res.error ??
          (isArabic ? "تعذّر فتح الشهادة حالياً" : "Could not open the certificate"),
      );
    }
  };

  if (src) {
    return (
      <div className="space-y-3">
        <iframe
          src={src}
          title={isArabic ? "الشهادة" : "Certificate"}
          className="h-[80vh] w-full rounded-xl border border-border bg-white"
        />
        <a
          href={src}
          target="_self"
          className="inline-flex items-center gap-1.5 text-small text-muted-foreground hover:text-primary transition-colors"
        >
          <ExternalLink className="size-3.5" />
          {isArabic ? "فتح بملء الشاشة" : "Open full screen"}
        </a>
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border py-14 text-center">
      <Award className="size-12 text-primary/60" />
      <p className="text-caption text-muted-foreground max-w-sm">
        {isArabic
          ? "شهادتك جاهزة. اضغط لفتحها داخل الموقع."
          : "Your certificate is ready. Open it inside the site."}
      </p>
      <button
        type="button"
        onClick={open}
        disabled={loading}
        className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60"
      >
        {loading ? (
          <Loader2 className="size-5 animate-spin" />
        ) : (
          <Award className="size-5" />
        )}
        {isArabic ? "فتح الشهادة" : "Open certificate"}
      </button>
      {error && <p className="text-caption text-red-500 max-w-sm">{error}</p>}
    </div>
  );
}
