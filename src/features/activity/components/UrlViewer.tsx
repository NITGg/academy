"use client";

import { Clock, ExternalLink, Globe, Link2, PlayCircle } from "lucide-react";
import type { UrlData } from "../types";

function fmtDate(ts: number, isArabic: boolean): string {
  const d = new Date(ts * 1000);
  return d.toLocaleString(isArabic ? "ar-EG" : "en-GB", {
    day: "numeric",
    month: "long",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function getEmbedUrl(rawUrl: string): string | null {
  if (!rawUrl) return null;

  try {
    const url = new URL(rawUrl);

    // YouTube
    if (url.hostname.includes("youtube.com")) {
      const v = url.searchParams.get("v");
      if (v) return `https://www.youtube.com/embed/${v}`;
      const embedMatch = url.pathname.match(/\/embed\/([^/?]+)/);
      if (embedMatch) return `https://www.youtube.com/embed/${embedMatch[1]}`;
    }
    if (url.hostname.includes("youtu.be")) {
      const id = url.pathname.replace(/^\//, "");
      if (id) return `https://www.youtube.com/embed/${id}`;
    }

    // Vimeo
    if (url.hostname.includes("vimeo.com")) {
      const match = url.pathname.match(/\/(\d+)/);
      if (match) return `https://player.vimeo.com/video/${match[1]}`;
    }
  } catch {
    /* ignore invalid URL format */
  }

  return null;
}

function getHostName(rawUrl: string): string {
  try {
    return new URL(rawUrl).hostname.replace(/^www\./, "");
  } catch {
    return rawUrl;
  }
}

export function UrlViewer({
  urlData,
  isArabic,
}: {
  urlData: UrlData;
  isArabic: boolean;
}) {
  const { name, intro, externalUrl, timemodified } = urlData;
  const embedUrl = getEmbedUrl(externalUrl);
  const hostName = getHostName(externalUrl);

  return (
    <div className="space-y-6">
      {/* Header Info Banner */}
      <div className="rounded-2xl border border-border bg-card p-6 shadow-sm md:p-8 space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border/60 pb-4">
          <div className="flex items-center gap-3">
            <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
              {embedUrl ? <PlayCircle className="size-6" /> : <Globe className="size-6" />}
            </div>
            <div>
              <h2 className="text-h3 font-bold text-foreground">{name}</h2>
              {timemodified ? (
                <div className="flex items-center gap-1.5 text-caption text-muted-foreground">
                  <Clock className="size-3.5" />
                  <span>
                    {isArabic ? "آخر تحديث:" : "Last updated:"}{" "}
                    {fmtDate(timemodified, isArabic)}
                  </span>
                </div>
              ) : null}
            </div>
          </div>
        </div>

        {/* Intro / Description */}
        {intro && (
          <div className="rounded-xl border border-primary/15 bg-primary/5 p-4 text-small text-muted-foreground leading-relaxed">
            <div
              className="prose prose-sm max-w-none dark:prose-invert"
              dangerouslySetInnerHTML={{ __html: intro }}
            />
          </div>
        )}

        {/* Embedded Player if YouTube or Vimeo */}
        {embedUrl && (
          <div className="overflow-hidden rounded-2xl border border-border bg-black shadow-md aspect-video">
            <iframe
              src={embedUrl}
              className="h-full w-full border-0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
              title={name}
            />
          </div>
        )}

        {/* Primary CTA Box */}
        <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-primary/30 bg-muted/30 py-8 px-4 text-center">
          <div className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
            <Link2 className="size-6" />
          </div>
          <div className="space-y-1">
            <p className="text-small font-semibold text-foreground">
              {isArabic ? "الرابط الخارجي:" : "External Link:"}
            </p>
            <p className="text-caption font-mono text-muted-foreground max-w-md truncate" title={externalUrl}>
              {hostName}
            </p>
          </div>
          <a
            href={externalUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 text-small font-semibold text-primary-foreground shadow-md hover:bg-primary/90 transition-all hover:scale-[1.02] active:scale-[0.98]"
          >
            <span>{isArabic ? "فتح الرابط في نافذة جديدة" : "Open Link in New Tab"}</span>
            <ExternalLink className="size-4" />
          </a>
        </div>
      </div>
    </div>
  );
}
