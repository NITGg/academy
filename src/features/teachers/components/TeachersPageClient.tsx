"use client";

import { useMemo, useState } from "react";
import { Search } from "lucide-react";
import { useRouter, usePathname } from "next/navigation";
import { useRef } from "react";
import type { Teacher } from "../types";
import { PaginatedTeacherList } from "./PaginatedTeacherList";

interface TeachersPageClientProps {
  teachers: Teacher[];
  locale: string;
  defaultSearch?: string;
}

export function TeachersPageClient({
  teachers,
  locale,
  defaultSearch = "",
}: TeachersPageClientProps) {
  const router = useRouter();
  const pathname = usePathname();
  const inputRef = useRef<HTMLInputElement>(null);
  const isAr = locale === "ar";

  const [selectedSubject, setSelectedSubject] = useState<string>("all");
  const [availableOnly, setAvailableOnly] = useState(false);

  // Extract unique subjects from all teachers
  const subjects = useMemo(() => {
    const set = new Set<string>();
    for (const t of teachers) {
      for (const s of t.subjects ?? []) {
        if (s.subject) set.add(s.subject);
      }
    }
    return Array.from(set).sort();
  }, [teachers]);

  // Filter teachers client-side
  const filtered = useMemo(() => {
    return teachers.filter((t) => {
      if (availableOnly && !t.available) return false;
      if (selectedSubject !== "all") {
        const hasSubject = (t.subjects ?? []).some(
          (s) => s.subject === selectedSubject,
        );
        if (!hasSubject) return false;
      }
      return true;
    });
  }, [teachers, selectedSubject, availableOnly]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const q = inputRef.current?.value.trim() ?? "";
    const params = new URLSearchParams();
    if (q) params.set("search", q);
    router.push(params.toString() ? `${pathname}?${params}` : pathname);
  };

  return (
    <div className="space-y-5">
      {/* Search bar */}
      <form onSubmit={handleSearch} className="relative max-w-md">
        <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <input
          ref={inputRef}
          type="search"
          defaultValue={defaultSearch}
          placeholder={isAr ? "ابحث عن مدرس..." : "Search teachers..."}
          className="h-10 w-full rounded-xl border border-input bg-muted/50 ps-9 pe-4 text-caption text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/50 transition-shadow"
          dir={isAr ? "rtl" : "ltr"}
        />
      </form>

      {/* Subject filter chips */}
      {subjects.length > 0 && (
        <div className="space-y-2">
          <p className="text-small font-semibold text-muted-foreground">
            {isAr ? "التخصص" : "Subject"}
          </p>
          <div className="flex flex-wrap gap-2">
            <Chip
              label={isAr ? "الكل" : "All"}
              active={selectedSubject === "all"}
              onClick={() => setSelectedSubject("all")}
            />
            {subjects.map((s) => (
              <Chip
                key={s}
                label={s}
                active={selectedSubject === s}
                onClick={() => setSelectedSubject(s)}
              />
            ))}
          </div>
        </div>
      )}

      {/* Available-only toggle */}
      <label className="inline-flex cursor-pointer items-center gap-2.5">
        <span
          role="checkbox"
          aria-checked={availableOnly}
          tabIndex={0}
          onClick={() => setAvailableOnly((v) => !v)}
          onKeyDown={(e) => e.key === " " && setAvailableOnly((v) => !v)}
          className={`relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-ring/50 ${
            availableOnly ? "bg-primary" : "bg-muted"
          }`}
        >
          <span
            className={`inline-block size-4 rounded-full bg-white shadow transition-transform ${
              availableOnly ? "translate-x-4" : "translate-x-0"
            }`}
          />
        </span>
        <span className="text-caption text-foreground">
          {isAr ? "المتاحون فقط" : "Available only"}
        </span>
      </label>

      {/* Count */}
      <p className="text-small text-muted-foreground">
        {isAr
          ? `${filtered.length} مدرس`
          : `${filtered.length} teacher${filtered.length !== 1 ? "s" : ""}`}
      </p>

      {/* Results */}
      {filtered.length === 0 ? (
        <div className="flex min-h-[300px] items-center justify-center rounded-2xl border border-border bg-card text-caption text-muted-foreground">
          {isAr ? "لا يوجد مدرسون يطابقون الفلاتر المحددة" : "No teachers match the selected filters"}
        </div>
      ) : (
        <PaginatedTeacherList teachers={filtered} locale={locale} pageSize={12} />
      )}
    </div>
  );
}

function Chip({
  label,
  active,
  onClick,
}: {
  label: string;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-full px-4 py-1.5 text-small font-medium transition-colors ${
        active
          ? "bg-primary text-primary-foreground"
          : "border border-border bg-muted/50 text-foreground hover:bg-muted"
      }`}
    >
      {label}
    </button>
  );
}
