"use client";

import { useRouter, usePathname } from "next/navigation";
import { useRef } from "react";
import { Search } from "lucide-react";

interface TeacherSearchProps {
  defaultValue?: string;
  locale: string;
}

export function TeacherSearch({ defaultValue, locale }: TeacherSearchProps) {
  const router = useRouter();
  const pathname = usePathname();
  const inputRef = useRef<HTMLInputElement>(null);
  const isAr = locale === "ar";

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const q = inputRef.current?.value.trim() ?? "";
    const params = new URLSearchParams();
    if (q) params.set("search", q);
    router.push(params.toString() ? `${pathname}?${params}` : pathname);
  };

  return (
    <form onSubmit={handleSubmit} className="relative max-w-md">
      <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
      <input
        ref={inputRef}
        type="search"
        defaultValue={defaultValue}
        placeholder={isAr ? "ابحث عن مدرس..." : "Search teachers..."}
        className="h-10 w-full rounded-xl border border-input bg-muted/50 ps-9 pe-4 text-caption text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/50 transition-shadow"
        dir={isAr ? "rtl" : "ltr"}
      />
    </form>
  );
}
