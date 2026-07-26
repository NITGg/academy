import type { Metadata } from "next";
import { CreditCard } from "lucide-react";

export const metadata: Metadata = { title: "الاشتراكات" };

export default function SubscriptionsPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <CreditCard className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الاشتراكات</h1>
      </div>
      <p className="text-muted-foreground text-caption">خطط الاشتراك المتاحة — قريباً.</p>
    </div>
  );
}
