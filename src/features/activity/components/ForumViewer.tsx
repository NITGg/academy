"use client";

import { MessageSquare, MessagesSquare } from "lucide-react";
import type { ActivityView } from "../types";

export function ForumViewer({
  activity,
  isArabic,
}: {
  activity: ActivityView;
  isArabic: boolean;
}) {
  const { name } = activity;

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
              ? "يمكنك المشاركة في النقاش وطرح أسئلتك والرد على مشاركات زملائك من خلال المنتدى."
              : "You can participate in the discussion, ask questions, and reply to your classmates' posts through the forum."}
          </p>
        </div>

        <div className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-4 py-1.5 text-caption font-medium text-amber-700 dark:text-amber-300">
          <MessagesSquare className="size-3.5" />
          <span>{isArabic ? "منتدى النقاش" : "Discussion Forum"}</span>
        </div>
      </div>
    </div>
  );
}
