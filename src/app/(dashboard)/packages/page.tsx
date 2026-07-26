import type { Metadata } from "next";
import { Package } from "lucide-react";

export const metadata: Metadata = { title: "الباقات" };

export default function PackagesPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Package className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الباقات</h1>
      </div>
      <p className="text-muted-foreground text-caption">باقات الحصص المرنة (فليكس) — قريباً.</p>
    </div>
  );
}
