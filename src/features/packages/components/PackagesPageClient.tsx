"use client";

import { useState } from "react";
import { Package, Calendar, Tag, CheckCircle2, Receipt, Clock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { AvailablePackage, MyPackage, PackagePaymentRecord } from "../types";

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
    used: { label: "مستخدمة بالكامل", className: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
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

// ── My Packages tab ──────────────────────────────────────────────────────────

function MyPackagesTab({ packages }: { packages: MyPackage[] }) {
  if (packages.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Package className="size-8 opacity-30" />
        <p className="text-caption">لا توجد باقات مشتراة</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {packages.map((pkg) => (
        <div key={pkg.id} className="rounded-2xl border border-border bg-card p-4 shadow-sm space-y-3">
          <div className="flex items-center justify-between gap-2">
            <StatusBadge status={pkg.status} />
            <span className="text-caption font-bold text-foreground">{pkg.name}</span>
          </div>

          <div className="space-y-1.5">
            <div className="flex items-center justify-between text-small text-muted-foreground">
              <span className="font-medium text-foreground tabular-nums">{pkg.remaining_flex}</span>
              <span>الحصص المتبقية: <span className="text-foreground font-medium">{pkg.remaining_flex} / {pkg.total_flex}</span></span>
            </div>
            <ProgressBar value={pkg.remaining_flex} max={pkg.total_flex} />
          </div>

          <div className="grid grid-cols-2 gap-2 text-[11px] text-muted-foreground">
            <div className="flex items-center gap-1.5">
              <Calendar className="size-3.5 shrink-0" />
              <span>تفعيل: {formatDate(pkg.timeactivated)}</span>
            </div>
            {pkg.expires_at > 0 && (
              <div className="flex items-center gap-1.5">
                <Calendar className="size-3.5 shrink-0" />
                <span>انتهاء: {formatDate(pkg.expires_at)}</span>
              </div>
            )}
            {pkg.expires_at <= 0 && (
              <div className="flex items-center gap-1.5">
                <Calendar className="size-3.5 shrink-0" />
                <span>انتهاء: بدون انتهاء</span>
              </div>
            )}
            <div className="flex items-center gap-1.5 col-span-2">
              <Tag className="size-3.5 shrink-0" />
              <span>المدفوع: {Number(pkg.price_paid).toFixed(2)} جنيه</span>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

// ── Available Packages tab ───────────────────────────────────────────────────

function AvailablePackagesTab({
  packages,
  hasActivePackage,
}: {
  packages: AvailablePackage[];
  hasActivePackage: boolean;
}) {
  if (packages.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Package className="size-8 opacity-30" />
        <p className="text-caption">لا توجد باقات متاحة حالياً</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {packages.map((pkg) => (
        <div key={pkg.id} className="rounded-2xl border border-border bg-card p-4 shadow-sm space-y-3">
          <div className="flex items-center justify-between gap-2">
            <span className="rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-semibold text-muted-foreground">
              {Number(pkg.flex_count)} حصة
            </span>
            <span className="text-caption font-bold text-foreground">{pkg.name}</span>
          </div>

          {pkg.description && (
            <p className="text-small text-muted-foreground leading-relaxed text-right">
              {pkg.description}
            </p>
          )}

          <div className="flex items-center justify-between text-small">
            <div className="flex items-center gap-1.5 text-muted-foreground">
              <Clock className="size-3.5" />
              <span>{pkg.expiration_days} يوم</span>
            </div>
            <div className="flex items-center gap-1.5">
              <Tag className="size-3.5 text-muted-foreground" />
              <span className="font-bold text-foreground">
                {pkg.offer
                  ? `${Number(pkg.offer.final).toFixed(2)} جنيه`
                  : `${Number(pkg.price).toFixed(2)} جنيه`}
              </span>
            </div>
          </div>

          <Button
            variant="default"
            size="lg"
            disabled={hasActivePackage}
            className="w-full rounded-xl"
          >
            شراء الباقة
          </Button>

          {hasActivePackage && (
            <p className="text-center text-[11px] text-muted-foreground">
              لديك بالفعل باقة نشطة
            </p>
          )}
        </div>
      ))}
    </div>
  );
}

// ── Payment History tab ──────────────────────────────────────────────────────

function PaymentHistoryTab({ records }: { records: PackagePaymentRecord[] }) {
  if (records.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Receipt className="size-8 opacity-30" />
        <p className="text-caption">لا يوجد سجل مدفوعات</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {records.map((rec) => (
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
  );
}

// ── Page root ────────────────────────────────────────────────────────────────

const TABS = [
  { id: "mine", label: "باقاتي" },
  { id: "available", label: "الباقات المتاحة" },
  { id: "history", label: "سجل الدفع" },
] as const;

type Tab = (typeof TABS)[number]["id"];

interface PackagesPageClientProps {
  myPackages: MyPackage[];
  availablePackages: AvailablePackage[];
  paymentHistory: PackagePaymentRecord[];
}

export function PackagesPageClient({
  myPackages,
  availablePackages,
  paymentHistory,
}: PackagesPageClientProps) {
  const [activeTab, setActiveTab] = useState<Tab>("mine");
  const hasActivePackage = myPackages.some((p) => p.status === "active");

  return (
    <div className="space-y-4">
      {/* Tabs */}
      <div className="flex border-b border-border">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={cn(
              "flex-1 py-2.5 text-small font-medium transition-colors",
              activeTab === tab.id
                ? "border-b-2 border-primary text-primary"
                : "text-muted-foreground hover:text-foreground"
            )}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Tab content */}
      {activeTab === "mine" && <MyPackagesTab packages={myPackages} />}
      {activeTab === "available" && (
        <AvailablePackagesTab packages={availablePackages} hasActivePackage={hasActivePackage} />
      )}
      {activeTab === "history" && <PaymentHistoryTab records={paymentHistory} />}
    </div>
  );
}
