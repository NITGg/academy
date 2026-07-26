import type { Metadata } from "next";
import { Video } from "lucide-react";

export const metadata: Metadata = { title: "حصصي" };

export default function LessonsPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Video className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">حصصي</h1>
      </div>
      <p className="text-muted-foreground text-caption">حصصك المحجوزة مع المدرسين — قريباً.</p>
    </div>
  );
}
