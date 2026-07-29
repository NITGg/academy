"use client";

import { useState } from "react";
import type { CalendarMonthView, CalendarDay } from "../types";
import { parseMlang } from "@/lib/mlang";
import { X, ExternalLink } from "lucide-react";

interface CalendarGridProps {
  view: CalendarMonthView;
  locale: string;
}

const EVENT_COLORS: Record<string, { dot: string; badge: string }> = {
  due: { dot: "bg-rose-500", badge: "bg-rose-500/10 text-rose-700 dark:text-rose-400" },
  course: { dot: "bg-blue-500", badge: "bg-blue-500/10 text-blue-700 dark:text-blue-400" },
  site: { dot: "bg-violet-500", badge: "bg-violet-500/10 text-violet-700 dark:text-violet-400" },
  user: { dot: "bg-emerald-500", badge: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-400" },
};

const FALLBACK_COLORS = { dot: "bg-muted-foreground", badge: "bg-muted text-muted-foreground" };

function getColors(type: string) {
  return EVENT_COLORS[type] ?? FALLBACK_COLORS;
}

function DayCell({
  day,
  locale,
  selected,
  onSelect,
}: {
  day: CalendarDay;
  locale: string;
  selected: boolean;
  onSelect: (day: CalendarDay) => void;
}) {
  const events = day.events ?? [];
  const hasEvents = events.length > 0;

  return (
    <button
      type="button"
      onClick={() => hasEvents && onSelect(day)}
      className={`relative flex w-full min-h-[80px] flex-col rounded-xl border p-2 text-start transition-colors ${
        selected
          ? "border-primary bg-primary/10 ring-1 ring-primary/40"
          : day.istoday
            ? "border-primary bg-primary/5 ring-1 ring-primary/30"
            : day.isweekend
              ? "border-border/50 bg-muted/20"
              : "border-border/40 bg-card"
      } ${hasEvents ? "cursor-pointer hover:border-primary/50 hover:bg-muted/30" : "cursor-default"}`}
    >
      <span
        className={`text-[11px] font-bold leading-none ${
          day.istoday ? "text-primary" : "text-foreground/70"
        }`}
      >
        {day.mday}
      </span>

      {hasEvents && (
        <div className="mt-1.5 flex flex-col gap-0.5 flex-1">
          {events.slice(0, 2).map((ev) => {
            const colors = getColors(ev.eventtype);
            const name = parseMlang(ev.name, locale as "ar" | "en");
            return (
              <div
                key={ev.id}
                className="flex items-start gap-1"
                title={name}
              >
                <span className={`mt-1 size-1.5 shrink-0 rounded-full ${colors.dot}`} />
                <span className="line-clamp-2 text-[10px] font-medium leading-tight text-foreground/80">
                  {name}
                </span>
              </div>
            );
          })}
          {events.length > 2 && (
            <span className="ps-2.5 text-[10px] text-primary font-medium">
              {locale === "ar" ? `+${events.length - 2} أحداث` : `+${events.length - 2} more`}
            </span>
          )}
        </div>
      )}
    </button>
  );
}

function EventDetailPanel({
  day,
  locale,
  onClose,
}: {
  day: CalendarDay;
  locale: string;
  onClose: () => void;
}) {
  const isAr = locale === "ar";
  const events = day.events ?? [];

  const dateLabel = new Date(day.timestamp * 1000).toLocaleDateString(
    isAr ? "ar-EG" : "en-US",
    { weekday: "long", year: "numeric", month: "long", day: "numeric" },
  );

  return (
    <div className="rounded-2xl border border-border bg-card shadow-sm overflow-hidden">
      <div className="flex items-center justify-between border-b border-border px-4 py-3">
        <h3 className="text-caption font-semibold text-foreground">{dateLabel}</h3>
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
        >
          <X className="size-4" />
        </button>
      </div>

      <div className="divide-y divide-border">
        {events.map((ev) => {
          const colors = getColors(ev.eventtype);
          const name = parseMlang(ev.name, locale as "ar" | "en");
          const courseName = ev.course?.fullname
            ? parseMlang(ev.course.fullname, locale as "ar" | "en")
            : null;

          return (
            <div key={ev.id} className="flex items-start gap-3 px-4 py-3">
              <span className={`mt-1.5 size-2 shrink-0 rounded-full ${colors.dot}`} />
              <div className="flex-1 min-w-0">
                <p className="text-caption font-medium text-foreground leading-snug">
                  {name}
                </p>
                {courseName && (
                  <p className="mt-0.5 text-small text-muted-foreground">{courseName}</p>
                )}
                {ev.timestart > 0 && (
                  <p className="mt-0.5 text-small text-muted-foreground">
                    {new Date(ev.timestart * 1000).toLocaleTimeString(
                      isAr ? "ar-EG" : "en-US",
                      { hour: "2-digit", minute: "2-digit" },
                    )}
                  </p>
                )}
                <span
                  className={`mt-1.5 inline-block rounded-full px-2 py-0.5 text-[10px] font-medium ${colors.badge}`}
                >
                  {isAr ? getTypeLabel(ev.eventtype, true) : getTypeLabel(ev.eventtype, false)}
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function getTypeLabel(type: string, isAr: boolean): string {
  const map: Record<string, [string, string]> = {
    due: ["موعد تسليم", "Due"],
    course: ["كورس", "Course"],
    site: ["الموقع", "Site"],
    user: ["شخصي", "Personal"],
  };
  const pair = map[type] ?? [type, type];
  return isAr ? pair[0] : pair[1];
}

export function CalendarGrid({ view, locale }: CalendarGridProps) {
  const isAr = locale === "ar";
  const [selectedDay, setSelectedDay] = useState<CalendarDay | null>(null);

  const handleSelect = (day: CalendarDay) => {
    setSelectedDay((prev) => (prev?.timestamp === day.timestamp ? null : day));
  };

  return (
    <div className="space-y-4">
      <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        {/* Day-name header */}
        <div className="grid grid-cols-7 border-b border-border bg-muted/30">
          {view.daynames.map((d, i) => (
            <div
              key={i}
              className="py-2.5 text-center text-[11px] font-semibold text-muted-foreground"
            >
              {d.shortname}
            </div>
          ))}
        </div>

        {/* Weeks */}
        <div className="p-3 space-y-2">
          {view.weeks.map((week, wi) => (
            <div key={wi} className="grid grid-cols-7 gap-1.5">
              {(week.prepadding ?? []).map((_, i) => (
                <div key={`pre-${i}`} />
              ))}

              {week.days.map((day) => (
                <DayCell
                  key={day.timestamp}
                  day={day}
                  locale={locale}
                  selected={selectedDay?.timestamp === day.timestamp}
                  onSelect={handleSelect}
                />
              ))}

              {(week.postpadding ?? []).map((_, i) => (
                <div key={`post-${i}`} />
              ))}
            </div>
          ))}
        </div>

        {/* Legend */}
        <div className="flex flex-wrap gap-3 border-t border-border px-4 py-3">
          {Object.entries(EVENT_COLORS).map(([type, colors]) => (
            <div key={type} className="flex items-center gap-1">
              <span className={`size-2 rounded-full ${colors.dot}`} />
              <span className="text-[10px] text-muted-foreground">
                {getTypeLabel(type, isAr)}
              </span>
            </div>
          ))}
        </div>
      </div>

      {/* Event detail panel — shown below when a day is selected */}
      {selectedDay && (selectedDay.events ?? []).length > 0 && (
        <EventDetailPanel
          day={selectedDay}
          locale={locale}
          onClose={() => setSelectedDay(null)}
        />
      )}
    </div>
  );
}
