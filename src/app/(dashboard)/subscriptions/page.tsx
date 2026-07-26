import type { Metadata } from "next";
import { CreditCard } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import {
  getAvailableSubscriptions,
  getMySubscriptions,
  getSubscriptionPaymentHistory,
} from "@/features/subscriptions/server";
import { SubscriptionsPageClient } from "@/features/subscriptions/components/SubscriptionsPageClient";

export const metadata: Metadata = { title: "الاشتراكات" };

export default async function SubscriptionsPage() {
  const session = await getSessionFromCookie();

  const [availableSubscriptions, mySubscriptions, paymentHistory] = await Promise.all([
    getAvailableSubscriptions(),
    session ? getMySubscriptions(session.wstoken) : Promise.resolve([]),
    session ? getSubscriptionPaymentHistory(session.wstoken) : Promise.resolve([]),
  ]);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <CreditCard className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الاشتراكات</h1>
      </div>

      <SubscriptionsPageClient
        mySubscriptions={mySubscriptions}
        availableSubscriptions={availableSubscriptions}
        paymentHistory={paymentHistory}
      />
    </div>
  );
}
