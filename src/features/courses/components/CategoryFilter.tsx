"use client";

import { useRouter, usePathname } from "next/navigation";
import type { CourseCategory } from "../types";

interface CategoryFilterProps {
  categories: CourseCategory[];
  activeCategoryId?: string;
  searchQuery?: string;
}

export function CategoryFilter({ categories, activeCategoryId, searchQuery }: CategoryFilterProps) {
  const router = useRouter();
  const pathname = usePathname();

  const navigate = (id: string | null) => {
    const params = new URLSearchParams();
    if (id) params.set("categoryId", id);
    if (searchQuery) params.set("search", searchQuery);
    const qs = params.toString();
    router.push(qs ? `${pathname}?${qs}` : pathname);
  };

  return (
    <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
      <button
        onClick={() => navigate(null)}
        className={`shrink-0 rounded-full px-4 py-1.5 text-xs font-semibold transition-colors ${
          !activeCategoryId
            ? "bg-primary text-primary-foreground"
            : "bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground"
        }`}
      >
        الكل
      </button>

      {categories.map((cat) => (
        <button
          key={cat.id}
          onClick={() => navigate(String(cat.id))}
          className={`shrink-0 rounded-full px-4 py-1.5 text-xs font-semibold transition-colors ${
            activeCategoryId === String(cat.id)
              ? "bg-primary text-primary-foreground"
              : "bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground"
          }`}
        >
          {cat.name}
          {cat.coursecount != null && cat.coursecount > 0 && (
            <span className="ms-1 opacity-60">({cat.coursecount})</span>
          )}
        </button>
      ))}
    </div>
  );
}
