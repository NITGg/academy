import Link from "next/link";
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
  Award,
  ChevronLeft,
  CheckCircle2,
  Circle,
  Lock,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { CourseModule } from "../types";

const MOD_ICONS: Record<string, LucideIcon> = {
  resource: FileText,
  resource2: Video,
  testnew: FileText,
  url: ExternalLink,
  page: BookOpen,
  quiz: ClipboardList,
  forum: MessageSquare,
  assign: PenLine,
  folder: Folder,
  book: BookOpen,
  lesson: Video,
  scorm: Package,
  customcert: Award,
};

export function CourseModuleRow({
  mod,
  courseId,
  enrolled,
}: {
  mod: CourseModule;
  courseId: number;
  enrolled: boolean;
}) {
  const Icon = MOD_ICONS[mod.modname] ?? File;
  // Completion state now comes straight from getalltopics (completionstate) with a
  // fallback to the older completiondata shape used by the core_course_get_contents path.
  const hasCompletion = mod.hascompletion || mod.completiondata != null;
  const completed =
    mod.completionstate === 1 ||
    mod.completionstate === 2 ||
    mod.completiondata?.state === 1;

  // Every activity now opens INSIDE the site at its own page (the page picks the right
  // viewer, or shows a graceful "coming soon" for types without one yet).
  const canOpen = enrolled && mod.uservisible !== false;
  const href = `/courses/${courseId}/activity/${mod.id}`;

  const base =
    "flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors group w-full text-start";

  const inner = (
    <>
      {/* Completion indicator */}
      {hasCompletion ? (
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
        <ChevronLeft className="size-4 shrink-0 text-muted-foreground/30 group-hover:text-primary transition-colors" />
      ) : null}
    </>
  );

  if (canOpen) {
    return (
      <Link href={href} className={`${base} hover:bg-muted/50 cursor-pointer`}>
        {inner}
      </Link>
    );
  }

  return <div className={`${base} ${enrolled ? "" : "opacity-70"}`}>{inner}</div>;
}
