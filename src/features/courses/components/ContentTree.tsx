import { BookOpen, ChevronDown, Lock } from "lucide-react";
import { getLocale } from "next-intl/server";
import type { CourseSection } from "../types";
import { CourseModuleRow } from "./CourseModuleRow";

interface ContentTreeProps {
  sections: CourseSection[];
  isEnrolled?: boolean;
}

export async function ContentTree({ sections, isEnrolled = false }: ContentTreeProps) {
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

      {/* Locked banner for non-enrolled users */}
      {!isEnrolled && (
        <div className="flex items-center gap-2 rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-caption font-medium text-amber-700 dark:text-amber-400">
          <Lock className="size-4 shrink-0" />
          <span>
            {locale === "ar"
              ? "سجّل في الكورس لفتح المحتوى والوصول إلى الدروس"
              : "Enrol in this course to unlock the content"}
          </span>
        </div>
      )}

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
                <CourseModuleRow key={mod.id} mod={mod} enrolled={isEnrolled} />
              ))}
            </div>
          </details>
        ))}
      </div>
    </div>
  );
}
