import type { Metadata } from "next";
import { Calendar } from "lucide-react";

export const metadata: Metadata = { title: "التقويم" };

export default function CalendarPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Calendar className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">التقويم</h1>
      </div>
      <p className="text-muted-foreground text-caption">أحداث الشهر وجدول الحصص — قريباً.</p>
    </div>
  );
}
