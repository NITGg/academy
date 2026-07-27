import type { Metadata } from "next";
import { CreditCard } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import {
  getAvailableSubscriptions,
  getMySubscriptions,
  getSubscriptionPaymentHistory,
  getMyB2bSubscriptions,
} from "@/features/subscriptions/server";
import { SubscriptionsPageClient } from "@/features/subscriptions/components/SubscriptionsPageClient";

export const metadata: Metadata = { title: "الاشتراكات" };

export default async function SubscriptionsPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string }>;
}) {
  const session = await getSessionFromCookie();
  const { tab } = await searchParams;

  const [availableSubscriptions, mySubscriptions, paymentHistory, myB2bSubscriptions] =
    await Promise.all([
      getAvailableSubscriptions(),
      session ? getMySubscriptions(session.wstoken) : Promise.resolve([]),
      session ? getSubscriptionPaymentHistory(session.wstoken) : Promise.resolve([]),
      session ? getMyB2bSubscriptions(session.wstoken) : Promise.resolve([]),
    ]);

  const initialTab =
    tab === "available" || tab === "b2b" || tab === "history" ? tab : undefined;

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
        myB2bSubscriptions={myB2bSubscriptions}
        isLoggedIn={Boolean(session?.wstoken)}
        initialTab={initialTab}
      />
    </div>
  );
}
