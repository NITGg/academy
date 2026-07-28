"use client";

import { useState } from "react";
import { Package, Calendar, Tag, CheckCircle2, Receipt, Clock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn, getAppUrl } from "@/lib/utils";
import type { AvailablePackage, MyPackage, PackagePaymentRecord } from "../types";
import { Pagination } from "@/components/ui/Pagination";
import { BuyPackageModal } from "./BuyPackageModal";

function formatDate(ts: number): string {
  if (!ts) return "غير محدد";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}

// Derive the real state the student should see. The backend may still report a
// package as "active" (or "cancelled") after its deadline has passed, so we
// compute expiry from the date and fully-used from remaining sessions here.
function getEffectiveStatus(pkg: MyPackage): string {
  const now = Math.floor(Date.now() / 1000);
  if (pkg.expires_at > 0 && pkg.expires_at < now) return "expired";
  if (pkg.status === "active" && (pkg.remaining_flex ?? 0) <= 0) return "used";
  return pkg.status;
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, { label: string; className: string }> = {
    active: { label: "نشطة", className: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
    expired: { label: "منتهية", className: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" },
    used: { label: "مستخدمة بالكامل", className: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
    cancelled: { label: "ملغاة", className: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" },
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

function MyPackagesTab({ packages, pageSize = 6 }: { packages: MyPackage[]; pageSize?: number }) {
  const [currentPage, setCurrentPage] = useState(1);

  if (packages.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Package className="size-8 opacity-30" />
        <p className="text-caption">لا توجد باقات مشتراة</p>
      </div>
    );
  }

  const totalPages = Math.ceil(packages.length / pageSize);
  const currentPackages = packages.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        {currentPackages.map((pkg) => (
          <div key={pkg.id} className="rounded-2xl border border-border bg-card p-4 shadow-sm space-y-3">
            <div className="flex items-center justify-between gap-2">
              <StatusBadge status={getEffectiveStatus(pkg)} />
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
      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={packages.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />
    </div>
  );
}

// ── Available Packages tab ───────────────────────────────────────────────────

function AvailablePackagesTab({
  packages,
  hasActivePackage,
  activePackage,
  isLoggedIn = true,
  pageSize = 6,
}: {
  packages: AvailablePackage[];
  hasActivePackage: boolean;
  activePackage?: MyPackage | null;
  isLoggedIn?: boolean;
  pageSize?: number;
}) {
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedPkg, setSelectedPkg] = useState<AvailablePackage | null>(null);

  if (packages.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Package className="size-8 opacity-30" />
        <p className="text-caption">لا توجد باقات متاحة حالياً</p>
      </div>
    );
  }

  const totalPages = Math.ceil(packages.length / pageSize);
  const currentPackages = packages.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {currentPackages.map((pkg) => {
          const isThisPackageActive =
            Boolean(activePackage) &&
            (Number(activePackage?.packageid) === Number(pkg.id) ||
              Number(activePackage?.id) === Number(pkg.id) ||
              activePackage?.name === pkg.name);

          return (
            <div
              key={pkg.id}
              onClick={() => setSelectedPkg(pkg)}
              className={cn(
                "cursor-pointer space-y-3 rounded-2xl border p-5 shadow-sm transition hover:shadow-md",
                isThisPackageActive
                  ? "border-emerald-500/40 bg-emerald-500/5 hover:border-emerald-500/60"
                  : "border-border bg-card hover:border-primary/50",
              )}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex flex-wrap items-center gap-1.5">
                  <span className="rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary whitespace-nowrap">
                    {pkg.flex_count} حصة
                  </span>
                  {isThisPackageActive ? (
                    <span className="rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 whitespace-nowrap">
                      باقة نشطة
                    </span>
                  ) : pkg.offer ? (
                    <span className="rounded-lg bg-destructive/10 px-2 py-1 text-xs font-bold text-destructive whitespace-nowrap">
                      {pkg.offer.label}
                    </span>
                  ) : null}
                </div>
                <h3 className="font-bold text-end text-foreground text-small">
                  {pkg.name}
                </h3>
              </div>

              <p className="text-xs text-muted-foreground text-end line-clamp-2">
                {pkg.description ||
                  "تمثل كل حصة مرنة درساً واحداً مع أي مدرس."}
              </p>

              <div className="flex items-center justify-between border-t border-border pt-2">
                <button
                  type="button"
                  disabled={hasActivePackage}
                  onClick={(e) => {
                    e.stopPropagation();
                    if (!isLoggedIn) {
                      window.location.assign(getAppUrl("/login"));
                      return;
                    }
                    setSelectedPkg(pkg);
                  }}
                  className="rounded-xl bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50 cursor-pointer"
                >
                  {isThisPackageActive
                    ? "باقة مفعلة"
                    : hasActivePackage
                      ? "لديك باقة مفعلة"
                      : "اشترك"}
                </button>

                <div>
                  {pkg.offer ? (
                    <div className="flex items-center gap-1.5">
                      <span className="text-[11px] text-muted-foreground line-through">
                        {pkg.offer.original} جنيه
                      </span>
                      <span className="text-sm font-extrabold text-foreground">
                        {pkg.offer.final} جنيه
                      </span>
                    </div>
                  ) : (
                    <span className="text-sm font-extrabold text-foreground">
                      {pkg.price} جنيه
                    </span>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={packages.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />

      {selectedPkg && (
        <BuyPackageModal
          packageId={selectedPkg.id}
          packageName={selectedPkg.name}
          basePrice={selectedPkg.offer ? Number(selectedPkg.offer.final) : Number(selectedPkg.price)}
          open={Boolean(selectedPkg)}
          onClose={() => setSelectedPkg(null)}
        />
      )}
    </div>
  );
}

// ── Payment History tab ──────────────────────────────────────────────────────

function PaymentHistoryTab({ records, pageSize = 8 }: { records: PackagePaymentRecord[]; pageSize?: number }) {
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
  isLoggedIn?: boolean;
}

export function PackagesPageClient({
  myPackages,
  availablePackages,
  paymentHistory,
  isLoggedIn = true,
}: PackagesPageClientProps) {
  const [activeTab, setActiveTab] = useState<Tab>("mine");
  // A package blocks new purchases only when it is genuinely usable — its
  // effective status is "active" (not past its deadline, still has sessions).
  // Expired packages (even with leftover sessions), used, and cancelled ones
  // must NOT block buying.
  const activePackage = myPackages.find((p) => getEffectiveStatus(p) === "active");
  const hasActivePackage = Boolean(activePackage);

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
        <AvailablePackagesTab
          packages={availablePackages}
          hasActivePackage={hasActivePackage}
          activePackage={activePackage}
          isLoggedIn={isLoggedIn}
        />
      )}
      {activeTab === "history" && <PaymentHistoryTab records={paymentHistory} />}
    </div>
  );
}
