"use client";

import {
  FileText,
  ExternalLink,
  BookOpen,
  ClipboardList,
  MessageSquare,
  PenLine,
  Folder,
  Video,
  Package,
  File,
  CheckCircle2,
  Circle,
  Lock,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { CourseModule } from "../types";
import { markModuleViewed } from "../actions";

const MOD_ICONS: Record<string, LucideIcon> = {
  resource: FileText,
  url: ExternalLink,
  page: BookOpen,
  quiz: ClipboardList,
  forum: MessageSquare,
  assign: PenLine,
  folder: Folder,
  book: BookOpen,
  lesson: Video,
  scorm: Package,
};

export function CourseModuleRow({
  mod,
  enrolled,
}: {
  mod: CourseModule;
  enrolled: boolean;
}) {
  const Icon = MOD_ICONS[mod.modname] ?? File;
  const completed = mod.completiondata?.state === 1;
  const target = mod.fileurl || mod.url;
  const canOpen = enrolled && Boolean(target);

  const handleOpen = () => {
    if (!canOpen) return;
    // Fire the "viewed" event so completion tracks — best-effort, don't await.
    if (mod.instance) void markModuleViewed(mod.modname, mod.instance);
    window.open(target!, "_blank", "noopener,noreferrer");
  };

  const base =
    "flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors group w-full text-start";

  const inner = (
    <>
      {/* Completion indicator */}
      {mod.completiondata != null ? (
        completed ? (
          <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
        ) : (
          <Circle className="size-4 shrink-0 text-muted-foreground/40" />
        )
      ) : (
        <div className="size-4 shrink-0" />
      )}

      <Icon className="size-4 shrink-0 text-muted-foreground" />

      <span className="flex-1 text-caption text-foreground leading-snug">{mod.name}</span>

      {!enrolled ? (
        <Lock className="size-3.5 shrink-0 text-muted-foreground/50" />
      ) : canOpen ? (
        <ExternalLink className="size-3.5 shrink-0 text-muted-foreground/0 group-hover:text-primary transition-colors" />
      ) : null}
    </>
  );

  if (canOpen) {
    return (
      <button type="button" onClick={handleOpen} className={`${base} hover:bg-muted/50 cursor-pointer`}>
        {inner}
      </button>
    );
  }

  return (
    <div className={`${base} ${enrolled ? "" : "opacity-70"}`}>{inner}</div>
  );
}
