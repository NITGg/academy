import type { Metadata } from "next";
import { Package } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { getAvailablePackages, getMyPackages, getPackagePaymentHistory } from "@/features/packages/server";
import { PackagesPageClient } from "@/features/packages/components/PackagesPageClient";

export const metadata: Metadata = { title: "الباقات" };

export default async function PackagesPage() {
  const session = await getSessionFromCookie();

  const [availablePackages, myPackages, paymentHistory] = await Promise.all([
    getAvailablePackages(),
    session ? getMyPackages(session.wstoken) : Promise.resolve([]),
    session ? getPackagePaymentHistory(session.wstoken) : Promise.resolve([]),
  ]);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Package className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الباقات</h1>
      </div>

      <PackagesPageClient
        myPackages={myPackages}
        availablePackages={availablePackages}
        paymentHistory={paymentHistory}
        isLoggedIn={Boolean(session?.wstoken)}
      />
    </div>
  );
}
