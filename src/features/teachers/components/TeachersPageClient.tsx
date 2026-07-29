"use client";

import { useState } from "react";
import { Search, X } from "lucide-react";
import { useRouter, usePathname } from "next/navigation";
import type { Teacher } from "../types";
import type { TeacherCategory } from "../server";
import { PaginatedTeacherList } from "./PaginatedTeacherList";
import { parseMlang } from "@/lib/mlang";

interface TeachersPageClientProps {
  teachers: Teacher[];
  categories: TeacherCategory[];
  years: string[];
  locale: string;
  defaultSearch?: string;
  activeCategoryId?: string;
  activeYear?: string;
}

export function TeachersPageClient({
  teachers,
  categories,
  years,
  locale,
  defaultSearch = "",
  activeCategoryId,
  activeYear,
}: TeachersPageClientProps) {
  const router = useRouter();
  const pathname = usePathname();
  const isAr = locale === "ar";
  const lang = isAr ? "ar" : "en";

  // Controlled input so we can show the active search value and X button
  const [searchValue, setSearchValue] = useState(defaultSearch);

  const isSearchActive = Boolean(defaultSearch);
  const isCategoryActive = Boolean(activeCategoryId);
  const isYearActive = Boolean(activeYear);

  const navigate = (overrides: {
    search?: string;
    categoryId?: string | null;
    year?: string | null;
  }) => {
    const params = new URLSearchParams();
    const search = "search" in overrides ? overrides.search : searchValue.trim();
    const cat =
      "categoryId" in overrides ? overrides.categoryId : activeCategoryId;
    const yr = "year" in overrides ? overrides.year : activeYear;

    if (search) params.set("search", search);
    if (cat) params.set("categoryId", cat);
    if (yr) params.set("year", yr);
    router.push(params.toString() ? `${pathname}?${params}` : pathname);
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    navigate({});
  };

  const clearSearch = () => {
    setSearchValue("");
    navigate({ search: "" });
  };

  return (
    <div className="space-y-5">
      {/* Search bar */}
      <div className="space-y-1.5">
        <form onSubmit={handleSearchSubmit} className="relative max-w-md">
          <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
          <input
            type="search"
            value={searchValue}
            onChange={(e) => {
              setSearchValue(e.target.value);
              if (!e.target.value) navigate({ search: "" });
            }}
            placeholder={isAr ? "ابحث عن مدرس..." : "Search teachers..."}
            className={`h-10 w-full rounded-xl border bg-muted/50 ps-9 pe-10 text-caption text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/50 transition-all ${
              isSearchActive
                ? "border-primary ring-1 ring-primary/30"
                : "border-input"
            }`}
            dir={isAr ? "rtl" : "ltr"}
          />
          {searchValue && (
            <button
              type="button"
              onClick={clearSearch}
              className="absolute end-3 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-muted-foreground hover:text-foreground transition-colors"
              aria-label={isAr ? "مسح البحث" : "Clear search"}
            >
              <X className="size-3.5" />
            </button>
          )}
        </form>

        {/* Active search indicator */}
        {isSearchActive && (
          <p className="text-small text-primary">
            {isAr
              ? `نتائج البحث عن "${defaultSearch}" — ${teachers.length} مدرس`
              : `Results for "${defaultSearch}" — ${teachers.length} teacher${teachers.length !== 1 ? "s" : ""}`}
          </p>
        )}
      </div>

      {/* التصنيفات — Category filter */}
      {categories.length > 0 && (
        <div className="space-y-2">
          <p className="text-small font-semibold text-muted-foreground">
            {isAr ? "التصنيفات" : "Categories"}
          </p>
          <div className="flex flex-wrap gap-2">
            <Chip
              label={isAr ? "الكل" : "All"}
              active={!isCategoryActive}
              onClick={() => navigate({ categoryId: null })}
            />
            {categories.map((cat) => (
              <Chip
                key={cat.id}
                label={parseMlang(cat.name, lang)}
                active={activeCategoryId === String(cat.id)}
                onClick={() => navigate({ categoryId: String(cat.id) })}
              />
            ))}
          </div>
        </div>
      )}

      {/* العام الدراسي — Academic year filter */}
      {years.length > 0 && (
        <div className="space-y-2">
          <p className="text-small font-semibold text-muted-foreground">
            {isAr ? "العام الدراسي" : "Academic year"}
          </p>
          <div className="flex flex-wrap gap-2">
            <Chip
              label={isAr ? "الكل" : "All"}
              active={!isYearActive}
              onClick={() => navigate({ year: null })}
            />
            {years.map((yr) => (
              <Chip
                key={yr}
                label={yr}
                active={activeYear === yr}
                onClick={() => navigate({ year: yr })}
              />
            ))}
          </div>
        </div>
      )}

      {/* Results */}
      {teachers.length === 0 ? (
        <div className="flex min-h-[300px] items-center justify-center rounded-2xl border border-border bg-card text-caption text-muted-foreground">
          {isAr
            ? "لا يوجد مدرسون يطابقون الفلاتر المحددة"
            : "No teachers match the selected filters"}
        </div>
      ) : (
        <PaginatedTeacherList teachers={teachers} locale={locale} pageSize={12} />
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
