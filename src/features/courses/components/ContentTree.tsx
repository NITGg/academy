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
  ChevronDown,
  CheckCircle2,
  Circle,
} from "lucide-react";
import { getLocale } from "next-intl/server";
import type { CourseSection, CourseModule } from "../types";

const MOD_ICONS: Record<string, typeof File> = {
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

function ModuleRow({ mod }: { mod: CourseModule }) {
  const Icon = MOD_ICONS[mod.modname] ?? File;
  const completed = mod.completiondata?.state === 1;

  return (
    <div className="flex items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-muted/50 transition-colors group">
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

      {/* Module type icon */}
      <Icon className="size-4 shrink-0 text-muted-foreground" />

      {/* Name */}
      <span className="flex-1 text-caption text-foreground leading-snug">
        {mod.name}
      </span>

      {/* Link indicator */}
      {mod.url && (
        <ExternalLink className="size-3.5 shrink-0 text-muted-foreground/0 group-hover:text-muted-foreground transition-colors" />
      )}
    </div>
  );
}

interface ContentTreeProps {
  sections: CourseSection[];
}

export async function ContentTree({ sections }: ContentTreeProps) {
  const locale = await getLocale();

  const visibleSections = sections.filter(
    (s) => s.modules && s.modules.length > 0
  );

  if (visibleSections.length === 0) {
    return (
      <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-16 text-center">
        <BookOpen className="size-10 text-muted-foreground/30" />
        <p className="text-caption text-muted-foreground">
          {locale === "ar"
            ? "محتوى الكورس غير متاح حالياً"
            : "Course content is not available yet"}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <h2 className="text-h2 font-bold">
        {locale === "ar" ? "محتوى الكورس" : "Course Content"}
      </h2>

      <p className="text-small text-muted-foreground">
        {visibleSections.length}{" "}
        {locale === "ar" ? "وحدة" : "sections"}{" ·  "}
        {visibleSections.reduce((acc, s) => acc + s.modules.length, 0)}{" "}
        {locale === "ar" ? "درس" : "lessons"}
      </p>

      <div className="space-y-2">
        {visibleSections.map((section, idx) => (
          <details
            key={section.id}
            className="group rounded-xl border border-border bg-card overflow-hidden"
            open={idx === 0}
          >
            <summary className="flex cursor-pointer list-none items-center gap-3 px-4 py-3 select-none hover:bg-muted/50 transition-colors">
              <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                {idx + 1}
              </span>
              <span className="flex-1 text-caption font-semibold text-foreground">
                {section.name || (locale === "ar" ? `الوحدة ${idx + 1}` : `Section ${idx + 1}`)}
              </span>
              <span className="text-[11px] text-muted-foreground">
                {section.modules.length}{" "}
                {locale === "ar" ? "درس" : "lessons"}
              </span>
              <ChevronDown className="size-4 text-muted-foreground transition-transform group-open:rotate-180" />
            </summary>

            <div className="border-t border-border px-2 pb-2 pt-1">
              {section.modules.map((mod) => (
                <ModuleRow key={mod.id} mod={mod} />
              ))}
            </div>
          </details>
        ))}
      </div>
    </div>
  );
}
