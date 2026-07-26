"use client";

import { useState, useMemo } from "react";
import { Receipt, Calendar, CreditCard, ChevronLeft, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import type { PaymentHistoryItem } from "../types";

function formatDate(ts: number): string {
  if (!ts) return "—";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${day}/${month}/${d.getFullYear()}`;
}

const STATUS_MAP: Record<string, { label: string; className: string }> = {
  paid: { label: "مكتمل", className: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
  completed: { label: "مكتمل", className: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
  pending: { label: "معلق", className: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
  failed: { label: "فشل", className: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" },
  refunded: { label: "مسترد", className: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400" },
  cancelled: { label: "ملغي", className: "bg-muted text-muted-foreground" },
};

function StatusBadge({ status }: { status: string }) {
  const s = STATUS_MAP[status] ?? { label: status, className: "bg-muted text-muted-foreground" };
  return (
    <span className={cn("rounded-full px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap", s.className)}>
      {s.label}
    </span>
  );
}

const FILTERS = [
  { id: "all", label: "الكل", statuses: null },
  { id: "paid", label: "مكتمل", statuses: ["paid", "completed"] },
  { id: "pending", label: "معلق", statuses: ["pending"] },
  { id: "failed", label: "فشل", statuses: ["failed"] },
  { id: "refunded", label: "مسترد", statuses: ["refunded"] },
] as const;

type FilterId = (typeof FILTERS)[number]["id"];

interface PaymentsPageClientProps {
  payments: PaymentHistoryItem[];
}

export function PaymentsPageClient({ payments }: PaymentsPageClientProps) {
  const [activeFilter, setActiveFilter] = useState<FilterId>("all");
  const [search, setSearch] = useState("");

  const filtered = useMemo(() => {
    const filter = FILTERS.find((f) => f.id === activeFilter);
    let list = payments;

    if (filter?.statuses) {
      list = list.filter((p) => filter.statuses!.includes(p.status));
    }

    const q = search.trim().toLowerCase();
    if (q) {
      list = list.filter(
        (p) =>
          p.invoice_number?.toLowerCase().includes(q) ||
          p.order_id.toLowerCase().includes(q) ||
          p.course_name?.toLowerCase().includes(q)
      );
    }

    return list;
  }, [payments, activeFilter, search]);

  return (
    <div className="space-y-4">
      {/* Search */}
      <div className="relative">
        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="ابحث برقم الفاتورة أو الدورة"
          className="w-full rounded-xl border border-border bg-card px-4 py-2.5 ps-10 text-small text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
        />
      </div>

      {/* Filter chips */}
      <div className="flex gap-2 overflow-x-auto pb-1">
        {FILTERS.map((f) => {
          const isActive = activeFilter === f.id;
          return (
            <button
              key={f.id}
              onClick={() => setActiveFilter(f.id)}
              className={cn(
                "shrink-0 rounded-full px-3.5 py-1.5 text-[12px] font-medium transition-colors",
                isActive
                  ? "bg-primary text-primary-foreground"
                  : "border border-border bg-card text-foreground hover:bg-muted"
              )}
            >
              {f.label}
            </button>
          );
        })}
      </div>

      {/* List */}
      {filtered.length === 0 ? (
        <div className="flex min-h-[280px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
          <Receipt className="size-10 opacity-20" />
          <p className="text-caption">لا توجد معاملات</p>
        </div>
      ) : (
        <div className="space-y-2">
          {filtered.map((item) => {
            const ref = item.invoice_number ?? item.order_id;
            const amount = `${item.currency} ${Number(item.amount).toFixed(2)}`;

            return (
              <div
                key={item.transaction_id}
                className="flex items-center gap-2 rounded-2xl border border-border bg-card px-4 py-3 shadow-sm"
              >
                {/* Chevron — placeholder for detail navigation */}
                <ChevronLeft className="size-4 shrink-0 text-muted-foreground/40" />

                {/* Card body */}
                <div className="min-w-0 flex-1">
                  {/* Row 1: reference number (start/right in RTL), status badge (end/left in RTL) */}
                  <div className="flex items-center justify-between gap-2">
                    <StatusBadge status={item.status} />
                    <span className="truncate text-[12px] font-bold text-foreground">{ref}</span>
                  </div>

                  {/* Course name if available */}
                  {item.course_name && (
                    <p className="mt-0.5 truncate text-end text-[11px] text-muted-foreground">
                      {item.course_name}
                    </p>
                  )}

                  {/* Row 2: date (end/left), amount (start/right) */}
                  <div className="mt-1.5 flex items-center justify-between gap-2">
                    <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                      <Calendar className="size-3.5 shrink-0" />
                      <span>{formatDate(item.timecreated)}</span>
                    </div>
                    <div className="flex items-center gap-1">
                      <span className="text-[13px] font-bold text-primary">{amount}</span>
                      <CreditCard className="size-3.5 text-muted-foreground/60" />
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
