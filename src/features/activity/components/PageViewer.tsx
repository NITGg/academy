"use client";

import {
  FileText,
  Clock,
  Download,
  File,
  FileCode,
  FileSpreadsheet,
  Image as ImageIcon,
  ExternalLink,
  Paperclip,
} from "lucide-react";
import type { PageData, PageFile } from "../types";

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

function fmtBytes(bytes: number): string {
  if (!bytes) return "";
  const mb = bytes / (1024 * 1024);
  return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}

function stripHtml(html: string): string {
  if (typeof document === "undefined") return html.replace(/<[^>]*>/g, "");
  const el = document.createElement("div");
  el.innerHTML = html;
  return el.textContent?.trim() ?? "";
}

function getFileIcon(filename: string, mimetype?: string) {
  const ext = filename.split(".").pop()?.toLowerCase() ?? "";
  if (["png", "jpg", "jpeg", "gif", "webp", "svg"].includes(ext) || mimetype?.startsWith("image/")) {
    return <ImageIcon className="size-5 text-sky-500" />;
  }
  if (["pdf"].includes(ext) || mimetype?.includes("pdf")) {
    return <FileText className="size-5 text-rose-500" />;
  }
  if (["xls", "xlsx", "csv"].includes(ext) || mimetype?.includes("excel") || mimetype?.includes("spreadsheet")) {
    return <FileSpreadsheet className="size-5 text-emerald-500" />;
  }
  if (["js", "ts", "json", "html", "css", "py"].includes(ext)) {
    return <FileCode className="size-5 text-amber-500" />;
  }
  return <File className="size-5 text-muted-foreground" />;
}

export function PageViewer({
  page,
  isArabic,
}: {
  page: PageData;
  isArabic: boolean;
}) {
  const { name, intro, content, contentfiles = [], introfiles = [], timemodified } = page;
  const allFiles: PageFile[] = [...introfiles, ...contentfiles];
  const cleanIntro = intro ? stripHtml(intro) : "";
  const cleanContent = content ? stripHtml(content) : "";
  const showIntro = Boolean(intro && cleanIntro && cleanIntro !== cleanContent);

  return (
    <div className="space-y-6">
      {/* Header Info Banner */}
      <div className="rounded-2xl border border-border bg-card p-6 shadow-sm md:p-8 space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border/60 pb-4">
          <div className="flex items-center gap-3">
            <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <FileText className="size-6" />
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

        {/* Intro / Description if present */}
        {showIntro && (
          <div className="rounded-xl border border-primary/15 bg-primary/5 p-4 text-small text-muted-foreground leading-relaxed">
            <div
              className="prose prose-sm max-w-none dark:prose-invert"
              dangerouslySetInnerHTML={{ __html: intro! }}
            />
          </div>
        )}

        {/* Main HTML Content */}
        {content ? (
          <div
            className="prose prose-slate max-w-none dark:prose-invert text-foreground leading-relaxed 
                       [&_img]:max-w-full [&_img]:rounded-xl [&_img]:shadow-sm [&_img]:my-4 
                       [&_iframe]:w-full [&_iframe]:aspect-video [&_iframe]:rounded-xl [&_iframe]:my-4
                       [&_a]:text-primary [&_a]:underline [&_a:hover]:opacity-80
                       [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-border [&_th]:p-2 [&_td]:border [&_td]:border-border [&_td]:p-2"
            dangerouslySetInnerHTML={{ __html: content }}
          />
        ) : (
          <div className="py-8 text-center text-caption text-muted-foreground">
            {isArabic ? "لا يوجد محتوى في هذه الصفحة." : "This page has no content."}
          </div>
        )}
      </div>

      {/* Attached Files List */}
      {allFiles.length > 0 && (
        <div className="rounded-2xl border border-border bg-card p-6 shadow-sm space-y-4">
          <div className="flex items-center gap-2 text-small font-semibold text-foreground">
            <Paperclip className="size-4 text-primary" />
            <span>{isArabic ? "الملفات المرفقة" : "Attached files"}</span>
            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary">
              {allFiles.length}
            </span>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            {allFiles.map((file, idx) => {
              const fileUrl = file.fileurl;
              return (
                <div
                  key={idx}
                  className="flex items-center justify-between gap-3 rounded-xl border border-border bg-muted/20 p-3.5 transition-colors hover:bg-muted/40"
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-background border border-border">
                      {getFileIcon(file.filename, file.mimetype)}
                    </div>
                    <div className="min-w-0">
                      <p className="truncate text-small font-medium text-foreground" title={file.filename}>
                        {file.filename}
                      </p>
                      {file.filesize > 0 && (
                        <p className="text-[11px] text-muted-foreground">
                          {fmtBytes(file.filesize)}
                        </p>
                      )}
                    </div>
                  </div>

                  {fileUrl && (
                    <a
                      href={fileUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary/10 px-3 py-1.5 text-caption font-medium text-primary hover:bg-primary/20 transition-colors"
                    >
                      <Download className="size-3.5" />
                      <span>{isArabic ? "تحميل" : "Download"}</span>
                    </a>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
