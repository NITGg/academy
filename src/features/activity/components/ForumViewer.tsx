"use client";

import { useState } from "react";
import { ExternalLink, Loader2, MessageSquare, MessagesSquare } from "lucide-react";
import type { ActivityView } from "../types";
import { getActivityAutologinUrl } from "../actions";

export function ForumViewer({
  activity,
  isArabic,
}: {
  activity: ActivityView;
  isArabic: boolean;
}) {
  const { cmid, name } = activity;
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleOpenForum = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getActivityAutologinUrl(cmid);
      if (res.url) {
        window.open(res.url, "_blank");
      } else {
        setError(
          res.error ??
            (isArabic ? "تعذّر فتح المنتدى" : "Could not open forum"),
        );
      }
    } catch {
      setError(isArabic ? "حدث خطأ غير متوقع" : "Unexpected error occurred");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="rounded-2xl border border-border bg-card p-6 shadow-sm md:p-8 space-y-6">
      <div className="flex items-center gap-3 border-b border-border/60 pb-4">
        <div className="flex size-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
          <MessageSquare className="size-6" />
        </div>
        <div>
          <h2 className="text-h3 font-bold text-foreground">{name}</h2>
          <p className="text-caption text-muted-foreground">
            {isArabic ? "منتدى النقاش والحوار" : "Discussion Forum"}
          </p>
        </div>
      </div>

      <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-amber-500/30 bg-amber-500/5 py-10 px-4 text-center">
        <div className="flex size-12 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
          <MessagesSquare className="size-6" />
        </div>
        <div className="space-y-1">
          <h3 className="text-small font-bold text-foreground">
            {isArabic ? "المشاركة في النقاش" : "Join the Discussion"}
          </h3>
          <p className="text-caption text-muted-foreground max-w-sm">
            {isArabic
              ? "اضغط أدناه لاستعراض مواضيع النقاش وكتابة مشاركاتك واستفساراتك"
              : "Click below to browse forum topics and post your questions and replies"}
          </p>
        </div>

        {error && (
          <div className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-caption text-red-600 dark:text-red-400">
            {error}
          </div>
        )}

        <button
          onClick={handleOpenForum}
          disabled={loading}
          className="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-6 py-3 text-small font-semibold text-white shadow-md hover:bg-amber-700 active:scale-[0.98] transition-all disabled:opacity-50"
        >
          {loading ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <ExternalLink className="size-4" />
          )}
          <span>{isArabic ? "دخول المنتدى" : "Enter Forum"}</span>
        </button>
      </div>
    </div>
  );
}
