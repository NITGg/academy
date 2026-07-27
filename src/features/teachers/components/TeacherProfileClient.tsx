"use client";

import { useState, useMemo } from "react";
import type { Teacher, TeacherHour } from "@/features/teachers/types";
import { bookLessonAction } from "@/features/teachers/actions";
import { User, Star, ChevronRight, ChevronLeft, Check } from "lucide-react";

// RTL grid: rightmost column = Saturday (index 0)
const DAYS_AR = ["س", "ح", "ن", "ث", "ر", "خ", "ج"]; // Sat Sun Mon Tue Wed Thu Fri
const DAY_FULL_AR = [
  "الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس", "الجمعة", "السبت",
]; // indexed by JS getDay() 0=Sun..6=Sat
const MONTHS_AR = [
  "يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو",
  "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر",
];

function parseHM(t: string): [number, number] {
  const [h, m] = t.split(":").map(Number);
  return [h, m ?? 0];
}

// Generates 60-minute time slots for a given date, following the mobile guide algorithm.
function getSlotsForDay(
  date: Date,
  teacherHours: TeacherHour[] | undefined,
  busyTimes: [number, number][] | undefined,
  minBookingMinutes: number,
): { label: string; unix: number; disabled: boolean }[] {
  const dayOfWeek = date.getDay(); // 0=Sun..6=Sat — same as teacher.hours[].dayofweek
  const hours = teacherHours ?? [];
  const busy = busyTimes ?? [];

  let startH = 8, startM = 0, endH = 20, endM = 0;

  if (hours.length > 0) {
    const entry = hours.find((h) => h.dayofweek === dayOfWeek);
    if (!entry) return []; // Teacher has hours configured but not on this day
    [startH, startM] = parseHM(entry.starttime);
    [endH, endM] = parseHM(entry.endtime);
  }
  // else: no hours configured → use default 08:00–20:00 for all days

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
    // Overlap: slot [slotUnix, slotEndUnix) intersects busy [s, e)
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

// Returns whether a given calendar day is within the 14-day booking window
// AND the teacher has working hours on that day.
function isDaySelectable(
  year: number,
  month: number,
  day: number,
  teacherHours: TeacherHour[] | undefined,
): boolean {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const d = new Date(year, month, day);
  if (d < today) return false;

  const maxDate = new Date(today);
  maxDate.setDate(today.getDate() + 13); // inclusive 14-day window
  if (d > maxDate) return false;

  const hours = teacherHours ?? [];
  if (hours.length === 0) return true; // default hours every day
  return hours.some((h) => h.dayofweek === d.getDay());
}

export function TeacherProfileClient({
  teacher,
  minBookingMinutes = 60,
}: {
  teacher: Teacher;
  minBookingMinutes?: number;
}) {
  const [selectedSubject, setSelectedSubject] = useState(
    teacher.subjects?.[0]?.subject ?? "",
  );
  const [currentYear, setCurrentYear] = useState(() => new Date().getFullYear());
  const [currentMonth, setCurrentMonth] = useState(() => new Date().getMonth());
  const [selectedDay, setSelectedDay] = useState<number | null>(
    () => new Date().getDate(),
  );
  const [selectedSlotUnix, setSelectedSlotUnix] = useState<number | null>(null);
  const [note, setNote] = useState("");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<{
    type: "success" | "error";
    text: string;
  } | null>(null);

  // Column offset for Saturday-first grid:
  // JS getDay(): 0=Sun,1=Mon,2=Tue,3=Wed,4=Thu,5=Fri,6=Sat
  // Grid column: 0=Sat,1=Sun,2=Mon,3=Tue,4=Wed,5=Thu,6=Fri
  // Mapping: (getDay() + 1) % 7
  const firstDayIndex =
    (new Date(currentYear, currentMonth, 1).getDay() + 1) % 7;
  const totalDays = new Date(currentYear, currentMonth + 1, 0).getDate();

  const handlePrevMonth = () => {
    if (currentMonth === 0) {
      setCurrentMonth(11);
      setCurrentYear((y) => y - 1);
    } else {
      setCurrentMonth((m) => m - 1);
    }
    setSelectedDay(null);
    setSelectedSlotUnix(null);
  };

  const handleNextMonth = () => {
    if (currentMonth === 11) {
      setCurrentMonth(0);
      setCurrentYear((y) => y + 1);
    } else {
      setCurrentMonth((m) => m + 1);
    }
    setSelectedDay(null);
    setSelectedSlotUnix(null);
  };

  const slots = useMemo(() => {
    if (!selectedDay) return [];
    const d = new Date(currentYear, currentMonth, selectedDay);
    return getSlotsForDay(d, teacher.hours, teacher.busy_times, minBookingMinutes);
  }, [selectedDay, currentYear, currentMonth, teacher.hours, teacher.busy_times, minBookingMinutes]);

  const handleDayClick = (dayNum: number) => {
    if (!isDaySelectable(currentYear, currentMonth, dayNum, teacher.hours)) return;
    setSelectedDay(dayNum);
    setSelectedSlotUnix(null);
    setMessage(null);
  };

  const handleSubmit = async () => {
    if (!selectedDay || selectedSlotUnix === null) {
      setMessage({ type: "error", text: "يرجى تحديد اليوم ووقت الحصة" });
      return;
    }
    if (!note.trim()) {
      setMessage({ type: "error", text: "يرجى كتابة ملاحظة الطلب" });
      return;
    }

    setLoading(true);
    setMessage(null);

    const res = await bookLessonAction({
      teacherid: teacher.userid,
      subject: selectedSubject,
      requested_time: selectedSlotUnix,
      note: note.trim(),
    });

    setLoading(false);

    if (res.success) {
      setMessage({ type: "success", text: "تم إرسال طلب حجز الحصة بنجاح!" });
      setSelectedSlotUnix(null);
      setNote("");
    } else {
      setMessage({
        type: "error",
        text: res.error || "حدث خطأ أثناء حجز الحصة",
      });
    }
  };

  const selectedDayLabel = selectedDay
    ? `${DAY_FULL_AR[new Date(currentYear, currentMonth, selectedDay).getDay()]} • ${selectedDay}-${String(currentMonth + 1).padStart(2, "0")}-${currentYear}`
    : null;

  const rating = teacher.rating ?? 0;

  return (
    <div className="mx-auto max-w-xl space-y-8 pb-12">
      {/* ── Profile Header ── */}
      <div className="flex flex-col items-center text-center">
        {teacher.photourl ? (
          <img
            src={teacher.photourl}
            alt={teacher.fullname}
            className="h-28 w-28 rounded-full border-4 border-blue-50/50 object-cover shadow-sm"
          />
        ) : (
          <div className="flex h-28 w-28 items-center justify-center rounded-full bg-blue-100/60 text-blue-600">
            <User className="h-14 w-14" />
          </div>
        )}

        <h1 className="mt-4 text-xl font-bold text-slate-900">
          {teacher.fullname}
        </h1>
        {teacher.headline && (
          <p className="mt-0.5 text-xs uppercase tracking-wide text-slate-400">
            {teacher.headline}
          </p>
        )}
        {teacher.experience && (
          <p className="mt-1 text-xs text-slate-500">خبرة: {teacher.experience}</p>
        )}

        <div className="mt-2 flex items-center gap-0.5">
          {[1, 2, 3, 4, 5].map((i) => (
            <Star
              key={i}
              className={`h-4 w-4 ${
                i <= Math.round(rating)
                  ? "fill-amber-400 text-amber-400"
                  : "fill-slate-200 text-slate-200"
              }`}
            />
          ))}
          {rating > 0 && (
            <span className="ms-1 text-xs text-slate-400">
              ({rating.toFixed(1)})
            </span>
          )}
        </div>
      </div>

      {/* ── Bio ── */}
      {teacher.bio && (
        <div className="rounded-2xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-600">
          {teacher.bio}
        </div>
      )}

      {/* ── Subjects ── */}
      {teacher.subjects && teacher.subjects.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-base font-bold text-slate-800">المواد</h2>
          <div className="flex flex-wrap gap-2">
            {teacher.subjects.map((sub) => {
              const isActive = selectedSubject === sub.subject;
              return (
                <button
                  key={sub.subject}
                  type="button"
                  onClick={() => setSelectedSubject(sub.subject)}
                  className={`inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-xs font-bold transition ${
                    isActive
                      ? "bg-blue-600 text-white shadow-md shadow-blue-500/20"
                      : "bg-slate-100 text-slate-700 hover:bg-slate-200"
                  }`}
                >
                  {sub.subject}
                  {isActive && <Check className="h-3.5 w-3.5" />}
                </button>
              );
            })}
          </div>
        </div>
      )}

      {/* ── Booking Section ── */}
      <div className="space-y-5">
        <h2 className="text-base font-bold text-slate-800">احجز حصة</h2>

        {/* Month Calendar */}
        <div className="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
          {/* Month navigation */}
          <div className="flex items-center justify-between pb-4">
            <button
              type="button"
              onClick={handlePrevMonth}
              className="rounded-full p-1.5 text-blue-600 hover:bg-blue-50"
            >
              <ChevronRight className="h-5 w-5" />
            </button>

            <span className="text-sm font-bold text-blue-600">
              {MONTHS_AR[currentMonth]} {currentYear}
            </span>

            <button
              type="button"
              onClick={handleNextMonth}
              className="rounded-full p-1.5 text-blue-600 hover:bg-blue-50"
            >
              <ChevronLeft className="h-5 w-5" />
            </button>
          </div>

          {/* Day-of-week headers */}
          <div className="grid grid-cols-7 text-center text-xs font-bold text-slate-400">
            {DAYS_AR.map((d, i) => (
              <div key={i} className="py-2">
                {d}
              </div>
            ))}
          </div>

          {/* Day grid */}
          <div className="grid grid-cols-7 gap-y-1 text-center text-xs">
            {[...Array(firstDayIndex)].map((_, i) => (
              <div key={`pad-${i}`} />
            ))}

            {[...Array(totalDays)].map((_, i) => {
              const dayNum = i + 1;
              const selectable = isDaySelectable(
                currentYear,
                currentMonth,
                dayNum,
                teacher.hours,
              );
              const isSelected = selectedDay === dayNum;

              return (
                <div key={dayNum} className="flex justify-center py-1">
                  <button
                    type="button"
                    disabled={!selectable}
                    onClick={() => handleDayClick(dayNum)}
                    className={`flex h-10 w-10 items-center justify-center rounded-full font-semibold transition ${
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

        {/* Selected day label */}
        {selectedDayLabel && (
          <p className="text-xs font-bold text-slate-700">{selectedDayLabel}</p>
        )}

        {/* Time Slots */}
        {selectedDay && (
          <>
            {slots.length === 0 ? (
              <p className="rounded-xl bg-slate-50 py-4 text-center text-xs text-slate-400">
                لا توجد مواعيد متاحة في هذا اليوم
              </p>
            ) : (
              <div className="grid grid-cols-4 gap-2.5">
                {slots.map((slot) => {
                  const isActive = selectedSlotUnix === slot.unix;
                  return (
                    <button
                      key={slot.unix}
                      type="button"
                      disabled={slot.disabled}
                      onClick={() => setSelectedSlotUnix(slot.unix)}
                      className={`rounded-xl border px-2 py-2.5 text-center text-xs font-bold transition ${
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
            )}
          </>
        )}

        {/* Note */}
        <div className="space-y-1.5 pt-1">
          <label className="text-xs font-bold text-slate-700">
            ملاحظة الطلب <span className="text-red-500">*</span>
          </label>
          <textarea
            rows={3}
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="اكتب سبب الطلب أو أي تفاصيل للمدرس..."
            className="w-full rounded-2xl border border-slate-100 bg-slate-50/60 p-3.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-blue-600 focus:bg-white focus:outline-none"
          />
        </div>

        {/* Feedback message */}
        {message && (
          <div
            className={`rounded-xl p-3 text-xs font-medium ${
              message.type === "success"
                ? "border border-emerald-200 bg-emerald-50 text-emerald-600"
                : "border border-red-200 bg-red-50 text-red-600"
            }`}
          >
            {message.text}
          </div>
        )}

        {/* Submit */}
        <button
          type="button"
          onClick={handleSubmit}
          disabled={loading || selectedSlotUnix === null}
          className="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-700 disabled:opacity-50"
        >
          {loading ? "جاري إرسال الطلب..." : "إرسال الطلب"}
          {!loading && <Check className="h-4 w-4" />}
        </button>
      </div>
    </div>
  );
}
