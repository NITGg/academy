"use client";

import { ChevronLeft, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";

interface PaginationProps {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
  totalItems?: number;
  pageSize?: number;
  className?: string;
}

export function Pagination({
  currentPage,
  totalPages,
  onPageChange,
  totalItems,
  pageSize,
  className,
}: PaginationProps) {
  if (totalPages <= 1) return null;

  const startItem = totalItems && pageSize ? (currentPage - 1) * pageSize + 1 : 0;
  const endItem = totalItems && pageSize ? Math.min(currentPage * pageSize, totalItems) : 0;

  // Generate page numbers array with ellipsis
  const getPageNumbers = () => {
    const pages: (number | string)[] = [];
    const maxVisible = 5;

    if (totalPages <= maxVisible) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      if (currentPage <= 3) {
        pages.push(1, 2, 3, 4, "...", totalPages);
      } else if (currentPage >= totalPages - 2) {
        pages.push(1, "...", totalPages - 3, totalPages - 2, totalPages - 1, totalPages);
      } else {
        pages.push(1, "...", currentPage - 1, currentPage, currentPage + 1, "...", totalPages);
      }
    }
    return pages;
  };

  return (
    <div
      className={cn(
        "flex flex-col items-center justify-between gap-3 pt-4 sm:flex-row border-t border-border/60",
        className
      )}
    >
      {totalItems && pageSize ? (
        <p className="text-caption text-muted-foreground">
          عرض <span className="font-semibold text-foreground">{startItem}</span> -{" "}
          <span className="font-semibold text-foreground">{endItem}</span> من إجمالي{" "}
          <span className="font-semibold text-foreground">{totalItems}</span>
        </p>
      ) : (
        <div />
      )}

      <div className="flex items-center gap-1.5 dir-rtl">
        {/* Previous page (RTL: ChevronRight points right to go back to prev page) */}
        <button
          type="button"
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage <= 1}
          className="flex size-9 items-center justify-center rounded-xl border border-border bg-card text-foreground transition-all hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
          aria-label="الصفحة السابقة"
        >
          <ChevronRight className="size-4" />
        </button>

        {/* Page Numbers */}
        <div className="flex items-center gap-1">
          {getPageNumbers().map((p, idx) => {
            if (typeof p === "string") {
              return (
                <span
                  key={`ellipsis-${idx}`}
                  className="flex size-9 items-center justify-center text-caption text-muted-foreground select-none"
                >
                  …
                </span>
              );
            }

            const isActive = p === currentPage;
            return (
              <button
                key={p}
                type="button"
                onClick={() => onPageChange(p)}
                className={cn(
                  "flex size-9 items-center justify-center rounded-xl text-small font-medium transition-all",
                  isActive
                    ? "bg-primary text-primary-foreground font-bold shadow-sm"
                    : "border border-border bg-card text-foreground hover:bg-muted"
                )}
              >
                {p}
              </button>
            );
          })}
        </div>

        {/* Next page (RTL: ChevronLeft points left to go forward to next page) */}
        <button
          type="button"
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage >= totalPages}
          className="flex size-9 items-center justify-center rounded-xl border border-border bg-card text-foreground transition-all hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
          aria-label="الصفحة التالية"
        >
          <ChevronLeft className="size-4" />
        </button>
      </div>
    </div>
  );
}
