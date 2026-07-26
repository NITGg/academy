import type { Metadata } from "next";
import { GraduationCap } from "lucide-react";

export const metadata: Metadata = { title: "البرامج" };

export default function ProgramsPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <GraduationCap className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">البرامج</h1>
      </div>
      <p className="text-muted-foreground text-caption">البرامج التعليمية والشهادات — قريباً.</p>
    </div>
  );
}
