import type { Metadata } from "next";
import { Users } from "lucide-react";

export const metadata: Metadata = { title: "المدرسون" };

export default function TeachersPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Users className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">المدرسون</h1>
      </div>
      <p className="text-muted-foreground text-caption">دليل المدرسين المتاحين — قريباً.</p>
    </div>
  );
}
