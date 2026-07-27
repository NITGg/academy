import type { CalendarMonthView, CalendarDay } from "../types";
import { parseMlang } from "@/lib/mlang";

interface CalendarGridProps {
  view: CalendarMonthView;
  locale: string;
}

const EVENT_COLORS: Record<string, string> = {
  due: "bg-rose-500",
  course: "bg-blue-500",
  site: "bg-violet-500",
  user: "bg-emerald-500",
};

function DayCell({ day, locale }: { day: CalendarDay; locale: string }) {
  const isAr = locale === "ar";
  const events = day.events ?? [];

  return (
    <div
      className={`relative flex min-h-[72px] flex-col rounded-xl border p-2 transition-colors ${
        day.istoday
          ? "border-primary bg-primary/5 ring-1 ring-primary/30"
          : day.isweekend
            ? "border-border/50 bg-muted/20"
            : "border-border/40 bg-card"
      }`}
    >
      <span
        className={`text-[11px] font-bold leading-none ${
          day.istoday ? "text-primary" : "text-foreground/70"
        }`}
      >
        {day.mday}
      </span>

      {events.length > 0 && (
        <div className="mt-1.5 flex flex-col gap-0.5">
          {events.slice(0, 2).map((ev) => (
            <div
              key={ev.id}
              className={`flex items-center gap-1 rounded px-1 py-0.5 ${
                ev.url ? "cursor-pointer hover:opacity-80" : ""
              }`}
              title={parseMlang(ev.name, locale as "ar" | "en")}
            >
              <span
                className={`size-1.5 shrink-0 rounded-full ${EVENT_COLORS[ev.eventtype] ?? "bg-muted-foreground"}`}
              />
              <span className="truncate text-[9px] font-medium text-foreground/80">
                {parseMlang(ev.name, locale as "ar" | "en")}
              </span>
            </div>
          ))}
          {events.length > 2 && (
            <span className="ps-1 text-[9px] text-muted-foreground">
              {isAr ? `+${events.length - 2} أحداث` : `+${events.length - 2} more`}
            </span>
          )}
        </div>
      )}
    </div>
  );
}

export function CalendarGrid({ view, locale }: CalendarGridProps) {
  const isAr = locale === "ar";

  return (
    <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
      {/* Day-name header */}
      <div className="grid grid-cols-7 border-b border-border bg-muted/30">
        {view.daynames.map((d, i) => (
          <div
            key={i}
            className="py-2 text-center text-[11px] font-semibold text-muted-foreground"
          >
            {d.shortname}
          </div>
        ))}
      </div>

      {/* Weeks */}
      <div className="p-3 space-y-2">
        {view.weeks.map((week, wi) => (
          <div key={wi} className="grid grid-cols-7 gap-1.5">
            {/* Pre-padding empty cells */}
            {(week.prepadding ?? []).map((_, i) => (
              <div key={`pre-${i}`} />
            ))}

            {/* Days */}
            {week.days.map((day) => (
              <DayCell key={day.timestamp} day={day} locale={locale} />
            ))}

            {/* Post-padding empty cells */}
            {(week.postpadding ?? []).map((_, i) => (
              <div key={`post-${i}`} />
            ))}
          </div>
        ))}
      </div>

      {/* Legend */}
      <div className="flex flex-wrap gap-3 border-t border-border px-4 py-3">
        {Object.entries(EVENT_COLORS).map(([type, color]) => {
          const labels: Record<string, string> = {
            due: isAr ? "موعد تسليم" : "Due",
            course: isAr ? "كورس" : "Course",
            site: isAr ? "الموقع" : "Site",
            user: isAr ? "شخصي" : "Personal",
          };
          return (
            <div key={type} className="flex items-center gap-1">
              <span className={`size-2 rounded-full ${color}`} />
              <span className="text-[10px] text-muted-foreground">{labels[type]}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
