"use client";

import { useRouter } from "next/navigation";
import { ChevronRight, ChevronLeft } from "lucide-react";

interface CalendarNavProps {
  periodname: string;
  prevYear: number;
  prevMonth: number;
  nextYear: number;
  nextMonth: number;
  locale: string;
}

export function CalendarNav({
  periodname,
  prevYear,
  prevMonth,
  nextYear,
  nextMonth,
  locale,
}: CalendarNavProps) {
  const router = useRouter();
  const isAr = locale === "ar";

  const go = (year: number, month: number) =>
    router.push(`/calendar?year=${year}&month=${month}`);

  const PrevIcon = isAr ? ChevronRight : ChevronLeft;
  const NextIcon = isAr ? ChevronLeft : ChevronRight;

  return (
    <div className="flex items-center gap-3">
      <button
        onClick={() => go(prevYear, prevMonth)}
        className="flex size-8 items-center justify-center rounded-lg border border-border bg-card transition-colors hover:bg-muted"
        aria-label={isAr ? "الشهر السابق" : "Previous month"}
      >
        <PrevIcon className="size-4 text-muted-foreground" />
      </button>

      <h2 className="min-w-[140px] text-center text-caption font-bold text-foreground">
        {periodname}
      </h2>

      <button
        onClick={() => go(nextYear, nextMonth)}
        className="flex size-8 items-center justify-center rounded-lg border border-border bg-card transition-colors hover:bg-muted"
        aria-label={isAr ? "الشهر التالي" : "Next month"}
      >
        <NextIcon className="size-4 text-muted-foreground" />
      </button>
    </div>
  );
}
