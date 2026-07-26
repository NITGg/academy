import type { Metadata } from "next";
import { Receipt } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { getPaymentHistory } from "@/features/payments/server";
import { PaymentsPageClient } from "@/features/payments/components/PaymentsPageClient";

export const metadata: Metadata = { title: "الفواتير" };

export default async function PaymentsPage() {
  const session = await getSessionFromCookie();

  const payments = session ? await getPaymentHistory(session.wstoken) : [];

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Receipt className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الفواتير</h1>
      </div>
      <PaymentsPageClient payments={payments} />
    </div>
  );
}
