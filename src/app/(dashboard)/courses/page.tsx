import type { Metadata } from "next";
import { BookOpen } from "lucide-react";

export const metadata: Metadata = { title: "دوراتي" };

export default function CoursesPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <BookOpen className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">دوراتي</h1>
      </div>
      <p className="text-muted-foreground text-caption">قائمة الكورسات المسجل فيها — قريباً.</p>
    </div>
  );
}
