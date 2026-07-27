"use client";

import { useState, useMemo } from "react";
import { ChevronRight, ChevronLeft } from "lucide-react";
import type { TeacherHour } from "@/features/teachers/types";

const DAYS_AR = ["س", "ح", "ن", "ث", "ر", "خ", "ج"]; // Sat Sun Mon Tue Wed Thu Fri
const DAY_FULL_AR = [
  "الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس", "الجمعة", "السبت",
];
const MONTHS_AR = [
  "يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو",
  "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر",
];

function parseHM(t: string): [number, number] {
  const [h, m] = t.split(":").map(Number);
  return [h, m ?? 0];
}

function getSlotsForDay(
  date: Date,
  teacherHours: TeacherHour[],
  busyTimes: [number, number][],
  minBookingMinutes: number,
): { label: string; unix: number; disabled: boolean }[] {
  const dayOfWeek = date.getDay();
  const hours = teacherHours;
  const busy = busyTimes;

  let startH = 8, startM = 0, endH = 20, endM = 0;

  if (hours.length > 0) {
    const entry = hours.find((h) => h.dayofweek === dayOfWeek);
    if (!entry) return [];
    [startH, startM] = parseHM(entry.starttime);
    [endH, endM] = parseHM(entry.endtime);
  }

  const nowSec = Math.floor(Date.now() / 1000);
  const minSec = minBookingMinutes * 60;
  const slots: { label: string; unix: number; disabled: boolean }[] = [];

  let cur = new Date(date);
  cur.setHours(startH, startM, 0, 0);
  const end = new Date(date);
  end.setHours(endH, endM, 0, 0);

  while (cur < end) {
    const slotUnix = Math.floor(cur.getTime() / 1000);
    const slotEndUnix = slotUnix + 3600;
    const tooClose = slotUnix < nowSec + minSec;
    const isBusy = busy.some(([s, e]) => slotUnix < e && slotEndUnix > s);

    const h = cur.getHours();
    const m = cur.getMinutes();
    const isPM = h >= 12;
    const displayH = h > 12 ? h - 12 : h === 0 ? 12 : h;
    const label = `${displayH}:${String(m).padStart(2, "0")} ${isPM ? "م" : "ص"}`;

    slots.push({ label, unix: slotUnix, disabled: tooClose || isBusy });
    cur = new Date(cur.getTime() + 3_600_000);
  }

  return slots;
}

function isDaySelectable(
  year: number,
  month: number,
  day: number,
  teacherHours: TeacherHour[],
): boolean {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const d = new Date(year, month, day);
  if (d < today) return false;
  const maxDate = new Date(today);
  maxDate.setDate(today.getDate() + 13);
  if (d > maxDate) return false;
  if (teacherHours.length === 0) return true;
  return teacherHours.some((h) => h.dayofweek === d.getDay());
}

interface SlotPickerProps {
  hours: TeacherHour[];
  busyTimes: [number, number][];
  minBookingMinutes?: number;
  onSelect: (unix: number | null) => void;
}

export function SlotPicker({
  hours,
  busyTimes,
  minBookingMinutes = 60,
  onSelect,
}: SlotPickerProps) {
  const [currentYear, setCurrentYear] = useState(() => new Date().getFullYear());
  const [currentMonth, setCurrentMonth] = useState(() => new Date().getMonth());
  const [selectedDay, setSelectedDay] = useState<number | null>(() => new Date().getDate());
  const [selectedSlotUnix, setSelectedSlotUnix] = useState<number | null>(null);

  const firstDayIndex = (new Date(currentYear, currentMonth, 1).getDay() + 1) % 7;
  const totalDays = new Date(currentYear, currentMonth + 1, 0).getDate();

  const slots = useMemo(() => {
    if (!selectedDay) return [];
    const d = new Date(currentYear, currentMonth, selectedDay);
    return getSlotsForDay(d, hours, busyTimes, minBookingMinutes);
  }, [selectedDay, currentYear, currentMonth, hours, busyTimes, minBookingMinutes]);

  const handleDayClick = (dayNum: number) => {
    if (!isDaySelectable(currentYear, currentMonth, dayNum, hours)) return;
    setSelectedDay(dayNum);
    setSelectedSlotUnix(null);
    onSelect(null);
  };

  const handleSlotClick = (unix: number) => {
    setSelectedSlotUnix(unix);
    onSelect(unix);
  };

  const prevMonth = () => {
    if (currentMonth === 0) { setCurrentMonth(11); setCurrentYear((y) => y - 1); }
    else { setCurrentMonth((m) => m - 1); }
    setSelectedDay(null);
    setSelectedSlotUnix(null);
    onSelect(null);
  };

  const nextMonth = () => {
    if (currentMonth === 11) { setCurrentMonth(0); setCurrentYear((y) => y + 1); }
    else { setCurrentMonth((m) => m + 1); }
    setSelectedDay(null);
    setSelectedSlotUnix(null);
    onSelect(null);
  };

  const selectedDayLabel = selectedDay
    ? `${DAY_FULL_AR[new Date(currentYear, currentMonth, selectedDay).getDay()]} • ${selectedDay}-${String(currentMonth + 1).padStart(2, "0")}-${currentYear}`
    : null;

  return (
    <div className="space-y-4">
      {/* Calendar */}
      <div className="rounded-3xl border border-slate-100 bg-white p-4 shadow-sm">
        <div className="flex items-center justify-between pb-3">
          <button type="button" onClick={prevMonth} className="rounded-full p-1.5 text-blue-600 hover:bg-blue-50">
            <ChevronRight className="h-5 w-5" />
          </button>
          <span className="text-sm font-bold text-blue-600">
            {MONTHS_AR[currentMonth]} {currentYear}
          </span>
          <button type="button" onClick={nextMonth} className="rounded-full p-1.5 text-blue-600 hover:bg-blue-50">
            <ChevronLeft className="h-5 w-5" />
          </button>
        </div>

        <div className="grid grid-cols-7 text-center text-xs font-bold text-slate-400">
          {DAYS_AR.map((d, i) => <div key={i} className="py-1">{d}</div>)}
        </div>

        <div className="grid grid-cols-7 gap-y-1 text-center text-xs">
          {[...Array(firstDayIndex)].map((_, i) => <div key={`pad-${i}`} />)}
          {[...Array(totalDays)].map((_, i) => {
            const dayNum = i + 1;
            const selectable = isDaySelectable(currentYear, currentMonth, dayNum, hours);
            const isSelected = selectedDay === dayNum;
            return (
              <div key={dayNum} className="flex justify-center py-0.5">
                <button
                  type="button"
                  disabled={!selectable}
                  onClick={() => handleDayClick(dayNum)}
                  className={`flex h-9 w-9 items-center justify-center rounded-full font-semibold transition ${
                    isSelected
                      ? "bg-blue-600 text-white shadow-md shadow-blue-500/30"
                      : selectable
                        ? "bg-blue-100/70 text-blue-700 hover:bg-blue-200/80"
                        : "cursor-default text-slate-300"
                  }`}
                >
                  {dayNum}
                </button>
              </div>
            );
          })}
        </div>
      </div>

      {/* Day label */}
      {selectedDayLabel && (
        <p className="text-end text-xs font-bold text-slate-700">{selectedDayLabel}</p>
      )}

      {/* Slots */}
      {selectedDay && (
        slots.length === 0 ? (
          <p className="rounded-xl bg-slate-50 py-3 text-center text-xs text-slate-400">
            لا توجد مواعيد متاحة في هذا اليوم
          </p>
        ) : (
          <div className="grid grid-cols-4 gap-2">
            {slots.map((slot) => {
              const isActive = selectedSlotUnix === slot.unix;
              return (
                <button
                  key={slot.unix}
                  type="button"
                  disabled={slot.disabled}
                  onClick={() => handleSlotClick(slot.unix)}
                  className={`rounded-xl border px-1.5 py-2 text-center text-xs font-bold transition ${
                    slot.disabled
                      ? "cursor-not-allowed border-transparent bg-slate-50 text-slate-300 line-through"
                      : isActive
                        ? "border-blue-600 bg-white text-blue-600 shadow-sm ring-1 ring-blue-600"
                        : "border-slate-200 bg-white text-slate-600 hover:border-blue-300 hover:bg-blue-50"
                  }`}
                >
                  {slot.label}
                </button>
              );
            })}
          </div>
        )
      )}
    </div>
  );
}
