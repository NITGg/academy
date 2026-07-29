import type { Metadata } from "next";
import { Ticket } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { getAvailableCoupons } from "@/features/coupons/server";
import { CouponsPageClient } from "@/features/coupons/components/CouponsPageClient";

export const metadata: Metadata = { title: "الكوبونات" };

export default async function CouponsPage() {
  const session = await getSessionFromCookie();
  const coupons = await getAvailableCoupons(session?.wstoken);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Ticket className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الكوبونات</h1>
      </div>
      <CouponsPageClient coupons={coupons} />
    </div>
  );
}
