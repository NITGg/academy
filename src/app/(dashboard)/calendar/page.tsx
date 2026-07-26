import type { Metadata } from "next";
import { Calendar } from "lucide-react";
import { getCalendarMonth } from "@/features/calendar/server";
import { CalendarNav } from "@/features/calendar/components/CalendarNav";
import { CalendarGrid } from "@/features/calendar/components/CalendarGrid";

export const metadata: Metadata = { title: "التقويم" };

interface CalendarPageProps {
  searchParams: Promise<{ year?: string; month?: string }>;
}

export default async function CalendarPage({ searchParams }: CalendarPageProps) {
  const params = await searchParams;
  const now = new Date();
  const year = parseInt(params.year ?? "") || now.getFullYear();
  const month = parseInt(params.month ?? "") || now.getMonth() + 1;

  const locale = "ar";
  const view = await getCalendarMonth(year, month);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Calendar className="size-6 text-primary" />
          <h1 className="text-h1 font-bold">التقويم</h1>
        </div>

        {view && (
          <CalendarNav
            periodname={view.periodname}
            prevYear={view.previousperiod.year}
            prevMonth={view.previousperiod.mon}
            nextYear={view.nextperiod.year}
            nextMonth={view.nextperiod.mon}
            locale={locale}
          />
        )}
      </div>

      {view ? (
        <CalendarGrid view={view} locale={locale} />
      ) : (
        <div className="flex min-h-[300px] items-center justify-center rounded-2xl border border-border bg-card text-muted-foreground text-caption">
          تعذّر تحميل التقويم. يرجى المحاولة لاحقاً.
        </div>
      )}
    </div>
  );
}
