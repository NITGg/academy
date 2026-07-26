import type { Metadata } from "next";
import { Receipt } from "lucide-react";

export const metadata: Metadata = { title: "سجل الدفع" };

export default function PaymentsPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Receipt className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">سجل الدفع</h1>
      </div>
      <p className="text-muted-foreground text-caption">سجل معاملاتك وفواتيرك — قريباً.</p>
    </div>
  );
}
