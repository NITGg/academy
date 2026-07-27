"use client";

import { useState } from "react";
import type { Teacher } from "@/features/teachers/types";
import { bookLessonAction } from "@/features/teachers/actions";

export function BookingForm({ teacher }: { teacher: Teacher }) {
  const [subject, setSubject] = useState(
    teacher.subjects?.[0]?.subject || ""
  );
  const [date, setDate] = useState("");
  const [time, setTime] = useState("");
  const [note, setNote] = useState("");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!date || !time || !subject) {
      setMessage({ type: "error", text: "يرجى ملء جميع الحقول المطلوبة" });
      return;
    }

    setLoading(true);
    setMessage(null);

    // Convert date + time string to Unix timestamp
    const requestedTimestamp = Math.floor(new Date(`${date}T${time}`).getTime() / 1000);

    const res = await bookLessonAction({
      teacherid: teacher.userid,
      subject,
      requested_time: requestedTimestamp,
      note,
    });

    setLoading(false);

    if (res.success) {
      setMessage({
        type: "success",
        text: "تم إرسال طلب حجز الحصة بنجاح!",
      });
      setDate("");
      setTime("");
      setNote("");
    } else {
      setMessage({
        type: "error",
        text: res.error || "حدث خطأ أثناء حجز الحصة",
      });
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {message && (
        <div
          className={`rounded-xl p-3 text-xs font-medium ${
            message.type === "success"
              ? "bg-emerald-500/10 text-emerald-600 border border-emerald-500/20"
              : "bg-destructive/10 text-destructive border border-destructive/20"
          }`}
        >
          {message.text}
        </div>
      )}

      {/* Select Subject */}
      <div>
        <label className="mb-1 block text-xs font-medium">المادة</label>
        <select
          value={subject}
          onChange={(e) => setSubject(e.target.value)}
          className="w-full rounded-xl border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary"
        >
          {teacher.subjects?.map((s, idx) => (
            <option key={idx} value={s.subject}>
              {s.subject}
            </option>
          ))}
        </select>
      </div>

      {/* Date Picker */}
      <div>
        <label className="mb-1 block text-xs font-medium">تاريخ الحصة</label>
        <input
          type="date"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          className="w-full rounded-xl border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>

      {/* Time Picker */}
      <div>
        <label className="mb-1 block text-xs font-medium">وقت الحصة</label>
        <input
          type="time"
          value={time}
          onChange={(e) => setTime(e.target.value)}
          className="w-full rounded-xl border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>

      {/* Note */}
      <div>
        <label className="mb-1 block text-xs font-medium">ملاحظات (اختياري)</label>
        <textarea
          rows={3}
          value={note}
          onChange={(e) => setNote(e.target.value)}
          placeholder="أدخل أي تفاصيل إضافية للمدرس..."
          className="w-full rounded-xl border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary resize-none"
        />
      </div>

      <button
        type="submit"
        disabled={loading}
        className="w-full rounded-xl bg-primary py-2.5 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
      >
        {loading ? "جاري الإرسال..." : "تأكيد طلب الحجز"}
      </button>
    </form>
  );
}