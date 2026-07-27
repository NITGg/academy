"use client";

import { useState } from "react";
import { CreditCard, Calendar, Tag, CheckCircle2, Receipt, Clock } from "lucide-react";
import { cn } from "@/lib/utils";
import type {
  MySubscription,
  SubscriptionPaymentRecord,
  AvailableSubscription,
  B2BSubscription,
} from "../types";
import { Pagination } from "@/components/ui/Pagination";
import { SubscriptionCatalog } from "./SubscriptionCatalog";
import { B2bManageTab } from "./B2bManageTab";

function formatDate(ts: number): string {
  if (!ts) return "غير محدد";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, { label: string; className: string }> = {
    active: { label: "نشطة", className: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
    expired: { label: "منتهية", className: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" },
    cancelled: { label: "ملغية", className: "bg-muted text-muted-foreground" },
  };
  const s = map[status] ?? { label: status, className: "bg-muted text-muted-foreground" };
  return (
    <span className={cn("rounded-full px-2.5 py-0.5 text-[11px] font-semibold", s.className)}>
      {s.label}
    </span>
  );
}

function ProgressBar({ value, max }: { value: number; max: number }) {
  const pct = max > 0 ? Math.min(100, (value / max) * 100) : 0;
  return (
    <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
      <div
        className="h-full rounded-full bg-emerald-500 transition-all"
        style={{ width: `${pct}%` }}
      />
    </div>
  );
}

// ── My Subscriptions tab ─────────────────────────────────────────────────────

function MySubscriptionsTab({ subscriptions, pageSize = 6 }: { subscriptions: MySubscription[]; pageSize?: number }) {
  const [currentPage, setCurrentPage] = useState(1);

  if (subscriptions.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <CreditCard className="size-8 opacity-30" />
        <p className="text-caption">لا توجد اشتراكات نشطة</p>
      </div>
    );
  }

  const totalPages = Math.ceil(subscriptions.length / pageSize);
  const currentSubscriptions = subscriptions.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        {currentSubscriptions.map((sub) => (
          <div key={sub.id} className="rounded-2xl border border-border bg-card p-4 shadow-sm space-y-3">
            <div className="flex items-center justify-between gap-2">
              <StatusBadge status={sub.status} />
              <span className="text-caption font-bold text-foreground">{sub.name}</span>
            </div>

            {sub.remaining_days != null && (
              <div className="space-y-1.5">
                <div className="flex items-center justify-between text-small">
                  <span className="font-bold text-primary">{sub.remaining_days} يوم</span>
                  <span className="text-muted-foreground">المتبقي:</span>
                </div>
                <ProgressBar value={sub.remaining_days} max={sub.duration_days} />
              </div>
            )}

            <div className="grid grid-cols-2 gap-2 text-[11px] text-muted-foreground">
              <div className="flex items-center gap-1.5">
                <Calendar className="size-3.5 shrink-0" />
                <span>تفعيل: {formatDate(sub.timeactivated)}</span>
              </div>
              <div className="flex items-center gap-1.5">
                <Calendar className="size-3.5 shrink-0" />
                <span className={sub.status === "expired" ? "text-red-500" : ""}>
                  انتهاء: {sub.expires_at > 0 ? formatDate(sub.expires_at) : "بدون انتهاء"}
                </span>
              </div>
              <div className="flex items-center gap-1.5 col-span-2">
                <Tag className="size-3.5 shrink-0" />
                <span>المدفوع: {Number(sub.price_paid).toFixed(2)} جنيه</span>
              </div>
            </div>
          </div>
        ))}
      </div>
      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={subscriptions.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />
    </div>
  );
}

// ── Payment History tab ──────────────────────────────────────────────────────

function PaymentHistoryTab({ records, pageSize = 8 }: { records: SubscriptionPaymentRecord[]; pageSize?: number }) {
  const [currentPage, setCurrentPage] = useState(1);

  if (records.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Receipt className="size-8 opacity-30" />
        <p className="text-caption">لا يوجد سجل مدفوعات</p>
      </div>
    );
  }

  const totalPages = Math.ceil(records.length / pageSize);
  const currentRecords = records.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        {currentRecords.map((rec) => (
          <div key={rec.id} className="rounded-2xl border border-border bg-card p-4 shadow-sm space-y-2">
            <div className="flex items-center justify-between gap-2">
              <CheckCircle2 className="size-5 shrink-0 text-emerald-500" />
              <span className="text-caption font-bold text-foreground">{rec.name}</span>
            </div>

            <div className="space-y-1.5 text-[11px] text-muted-foreground">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1.5">
                  <Receipt className="size-3.5" />
                  <span className="font-mono">{rec.transaction_no}</span>
                </div>
                <div className="flex items-center gap-1.5">
                  <Clock className="size-3.5" />
                  <span>{formatDate(rec.timecreated)}</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span>{rec.method}</span>
                <span className="font-bold text-primary">{Number(rec.amount).toFixed(2)} جنيه</span>
              </div>
            </div>
          </div>
        ))}
      </div>
      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={records.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />
    </div>
  );
}

// ── Page root ────────────────────────────────────────────────────────────────

type Tab = "mine" | "available" | "b2b" | "history";

interface SubscriptionsPageClientProps {
  mySubscriptions: MySubscription[];
  availableSubscriptions: AvailableSubscription[];
  paymentHistory: SubscriptionPaymentRecord[];
  myB2bSubscriptions: B2BSubscription[];
  isLoggedIn: boolean;
  initialTab?: Tab;
}

export function SubscriptionsPageClient({
  mySubscriptions,
  availableSubscriptions,
  paymentHistory,
  myB2bSubscriptions,
  isLoggedIn,
  initialTab,
}: SubscriptionsPageClientProps) {
  const hasB2b = myB2bSubscriptions.length > 0;
  const [activeTab, setActiveTab] = useState<Tab>(
    initialTab === "b2b" && !hasB2b ? "available" : (initialTab ?? "mine"),
  );
  // When a card's "manage B2B" button is clicked, remember which plan to open.
  const [manageSubId, setManageSubId] = useState<number | undefined>(undefined);

  const hasActiveSubscription = mySubscriptions.some((s) => s.status === "active");

  // Set of subscription plan ids the user administers as an (active) B2B admin.
  const ownedB2bSubIds = new Set(
    myB2bSubscriptions.filter((s) => s.status === "active").map((s) => s.subscriptionid),
  );

  const tabs: { id: Tab; label: string }[] = [
    { id: "mine", label: "اشتراكاتي" },
    { id: "available", label: "الاشتراكات المتاحة" },
    ...(hasB2b ? [{ id: "b2b" as Tab, label: "إدارة اشتراك B2B" }] : []),
    { id: "history", label: "سجل الدفع" },
  ];

  const handleManageB2b = (subscriptionId: number) => {
    setManageSubId(subscriptionId);
    setActiveTab("b2b");
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-4">
      {/* Tabs */}
      <div className="flex overflow-x-auto border-b border-border scrollbar-none">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={cn(
              "flex-1 whitespace-nowrap px-3 py-2.5 text-small font-medium transition-colors",
              activeTab === tab.id
                ? "border-b-2 border-primary text-primary"
                : "text-muted-foreground hover:text-foreground",
            )}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Tab content */}
      {activeTab === "mine" && <MySubscriptionsTab subscriptions={mySubscriptions} />}
      {activeTab === "available" && (
        <SubscriptionCatalog
          subscriptions={availableSubscriptions}
          isLoggedIn={isLoggedIn}
          hasActiveSubscription={hasActiveSubscription}
          ownedB2bSubIds={ownedB2bSubIds}
          onManageB2b={handleManageB2b}
        />
      )}
      {activeTab === "b2b" && hasB2b && (
        <B2bManageTab subscriptions={myB2bSubscriptions} initialSubscriptionId={manageSubId} />
      )}
      {activeTab === "history" && <PaymentHistoryTab records={paymentHistory} />}
    </div>
  );
}
