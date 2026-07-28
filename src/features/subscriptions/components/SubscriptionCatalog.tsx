"use client";

import { useState } from "react";
import { Building2, X, Loader2, Check, Settings2 } from "lucide-react";
import { Pagination } from "@/components/ui/Pagination";
import { cn } from "@/lib/utils";
import { startSubscriptionCheckout } from "../actions";
import type { AvailableSubscription, MySubscription, B2BSubscription } from "../types";
import { BuySubscriptionModal } from "./BuySubscriptionModal";

interface SubscriptionCatalogProps {
  subscriptions: AvailableSubscription[];
  mySubscriptions?: MySubscription[];
  myB2bSubscriptions?: B2BSubscription[];
  isLoggedIn: boolean;
  hasActiveSubscription: boolean;
  /** subscription ids the current user administers as a B2B admin. */
  ownedB2bSubIds: Set<number>;
  /** called when a user clicks "إدارة اشتراك B2B" on a plan they already own. */
  onManageB2b: (subscriptionId: number) => void;
  pageSize?: number;
}

function UsersIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
  );
}

export function SubscriptionCatalog({
  subscriptions,
  mySubscriptions = [],
  myB2bSubscriptions = [],
  isLoggedIn,
  hasActiveSubscription,
  ownedB2bSubIds,
  onManageB2b,
  pageSize = 6,
}: SubscriptionCatalogProps) {
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedSub, setSelectedSub] = useState<AvailableSubscription | null>(null);
  const [buyModalConfig, setBuyModalConfig] = useState<{
    subscription: AvailableSubscription;
    type: "normal" | "b2b";
  } | null>(null);

  const activeNormalSubIds = new Set(
    mySubscriptions
      .filter((s) => s.status === "active" && s.type !== "b2b")
      .map((s) => s.subscriptionid),
  );

  const activeB2bSubIds = new Set([
    ...myB2bSubscriptions.filter((s) => s.status === "active").map((s) => s.subscriptionid),
    ...mySubscriptions.filter((s) => s.type === "b2b" && s.status === "active").map((s) => s.subscriptionid),
    ...Array.from(ownedB2bSubIds),
  ]);

  const hasActiveB2b = activeB2bSubIds.size > 0;

  if (subscriptions.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Building2 className="size-8 opacity-30" />
        <p className="text-caption">لا توجد اشتراكات متاحة حالياً</p>
      </div>
    );
  }

  const totalPages = Math.ceil(subscriptions.length / pageSize);
  const currentSubscriptions = subscriptions.slice(
    (currentPage - 1) * pageSize,
    currentPage * pageSize,
  );

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-4">

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {currentSubscriptions.map((sub) => {
          const isThisNormalSubActive = activeNormalSubIds.has(sub.id);
          const ownsB2b = activeB2bSubIds.has(sub.id);
          const b2bEnabled = Number(sub.b2b_enabled) === 1;
          const showB2bButton = ownsB2b || b2bEnabled;
          const isThisSubActive = isThisNormalSubActive || ownsB2b;
          const isUserSubActive = hasActiveSubscription;

          return (
            <div
              key={sub.id}
              onClick={() => setSelectedSub(sub)}
              className={cn(
                "cursor-pointer space-y-4 rounded-2xl border bg-card p-5 shadow-sm transition hover:shadow-md",
                isThisSubActive
                  ? "border-emerald-500/40 bg-emerald-500/5 hover:border-emerald-500/60"
                  : "border-border hover:border-primary/50",
              )}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex flex-wrap items-center gap-1.5">
                  <span className="rounded-lg bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 whitespace-nowrap">
                    {sub.duration_days} يوم
                  </span>
                  {isThisNormalSubActive ? (
                    <span className="rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 whitespace-nowrap">
                      اشتراك نشط
                    </span>
                  ) : ownsB2b ? (
                    <span className="rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 whitespace-nowrap">
                      اشتراك B2B مملوك
                    </span>
                  ) : sub.offer ? (
                    <span className="rounded-lg bg-destructive/10 px-2 py-1 text-xs font-bold text-destructive whitespace-nowrap">
                      {sub.offer.label}
                    </span>
                  ) : null}
                </div>
                <h3 className="font-bold text-end text-foreground text-small">{sub.name}</h3>
              </div>

              {sub.description && (
                <p className="text-xs text-muted-foreground text-end line-clamp-2">
                  {sub.description}
                </p>
              )}

              <div className="flex items-center justify-between border-t border-border pt-2">
                <span className="text-xs text-muted-foreground">السعر الفردي:</span>
                <div>
                  {sub.offer ? (
                    <div className="flex items-center gap-1.5">
                      <span className="text-[11px] text-muted-foreground line-through">
                        {sub.offer.original} جنيه
                      </span>
                      <span className="text-sm font-extrabold text-foreground">
                        {sub.offer.final} جنيه
                      </span>
                    </div>
                  ) : (
                    <span className="text-sm font-extrabold text-foreground">{sub.price} جنيه</span>
                  )}
                </div>
              </div>

              {/* ── Purchase buttons ── */}
              <div className="flex items-center gap-2 pt-1" onClick={(e) => e.stopPropagation()}>
                {/* Normal Subscription Button */}
                <button
                  type="button"
                  disabled={isUserSubActive}
                  onClick={() => {
                    if (!isLoggedIn) {
                      window.location.assign("/login");
                      return;
                    }
                    setBuyModalConfig({ subscription: sub, type: "normal" });
                  }}
                  className={cn(
                    "flex items-center justify-center gap-1 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50 cursor-pointer",
                    showB2bButton ? "flex-1" : "w-full",
                  )}
                >
                  {isThisNormalSubActive ? "مشترك" : isUserSubActive ? "لديك اشتراك" : "اشتراك فردي"}
                </button>

                {/* B2B button — only for plans that allow B2B; becomes "manage" when already owned */}
                {ownsB2b ? (
                  <button
                    type="button"
                    onClick={() => onManageB2b(sub.id)}
                    className="flex-1 flex items-center justify-center gap-1 rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-3 py-2 text-xs font-bold text-emerald-600 transition hover:bg-emerald-500/10 cursor-pointer"
                  >
                    <Settings2 className="size-3.5" />
                    إدارة اشتراك B2B
                  </button>
                ) : b2bEnabled ? (
                  <button
                    type="button"
                    disabled={hasActiveB2b}
                    onClick={() => {
                      if (hasActiveB2b) return;
                      if (!isLoggedIn) {
                        window.location.assign("/login");
                        return;
                      }
                      setBuyModalConfig({ subscription: sub, type: "b2b" });
                    }}
                    className={cn(
                      "flex-1 flex items-center justify-center gap-1 rounded-xl border px-3 py-2 text-xs font-bold transition",
                      hasActiveB2b
                        ? "border-muted bg-muted/20 text-muted-foreground opacity-60 cursor-not-allowed"
                        : "border-primary/30 bg-primary/5 text-primary hover:bg-primary/10 cursor-pointer",
                    )}
                  >
                    <Building2 className="size-3.5" />
                    {hasActiveB2b ? "لديك اشتراك B2B" : "اشتراك B2B"}
                  </button>
                ) : null}
              </div>
            </div>
          );
        })}
      </div>

      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={subscriptions.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />

      {/* ── Subscription Details Modal ── */}
      {selectedSub && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl border border-border space-y-4 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <h3 className="text-base font-bold text-foreground">{selectedSub.name}</h3>
              <button onClick={() => setSelectedSub(null)} className="text-muted-foreground hover:text-foreground">
                <X className="size-5" />
              </button>
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between rounded-xl bg-blue-500/5 p-3">
                <span className="text-xs font-bold text-blue-600">مدة الاشتراك:</span>
                <span className="text-sm font-extrabold text-blue-600">{selectedSub.duration_days} يوم</span>
              </div>

              {selectedSub.description && (
                <p className="text-xs text-muted-foreground leading-relaxed">{selectedSub.description}</p>
              )}

              {selectedSub.courses && selectedSub.courses.length > 0 && (
                <div className="space-y-1.5">
                  <h4 className="text-xs font-bold text-foreground">الكورسات المشمولة:</h4>
                  <div className="max-h-32 overflow-y-auto rounded-xl border border-border divide-y divide-border">
                    {selectedSub.courses.map((c) => (
                      <div key={c.id} className="p-2.5 text-xs text-foreground flex items-center gap-2">
                        <Check className="size-3.5 text-emerald-500 shrink-0" />
                        <span className="truncate">{c.fullname}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <div className="flex items-center justify-between rounded-xl bg-muted/40 p-3 text-xs">
                <span className="text-muted-foreground">سعر الفرد:</span>
                <div>
                  {selectedSub.offer ? (
                    <div className="flex items-center gap-1.5">
                      <span className="text-muted-foreground line-through">
                        {selectedSub.offer.original} جنيه
                      </span>
                      <span className="font-bold text-foreground">{selectedSub.offer.final} جنيه</span>
                    </div>
                  ) : (
                    <span className="font-bold text-foreground">{selectedSub.price} جنيه</span>
                  )}
                </div>
              </div>
            </div>

            <div className="flex items-center gap-2 pt-2">
              <button
                type="button"
                disabled={hasActiveSubscription}
                onClick={() => {
                  const subToBuy = selectedSub;
                  setSelectedSub(null);
                  if (!isLoggedIn) {
                    window.location.assign("/login");
                    return;
                  }
                  setBuyModalConfig({ subscription: subToBuy, type: "normal" });
                }}
                className={cn(
                  "flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50 cursor-pointer",
                  activeB2bSubIds.has(selectedSub.id) || Number(selectedSub.b2b_enabled) === 1
                    ? "flex-1"
                    : "w-full",
                )}
              >
                {activeNormalSubIds.has(selectedSub.id)
                  ? "مشترك"
                  : hasActiveSubscription
                    ? "لديك اشتراك نشط"
                    : "اشتراك فردي"}
              </button>

              {activeB2bSubIds.has(selectedSub.id) ? (
                <button
                  type="button"
                  onClick={() => {
                    const id = selectedSub.id;
                    setSelectedSub(null);
                    onManageB2b(id);
                  }}
                  className="flex-1 flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-4 py-2.5 text-xs font-bold text-emerald-600 transition hover:bg-emerald-500/10 cursor-pointer"
                >
                  <Settings2 className="size-4" />
                  إدارة اشتراك B2B
                </button>
              ) : Number(selectedSub.b2b_enabled) === 1 ? (
                <button
                  type="button"
                  disabled={hasActiveB2b}
                  onClick={() => {
                    if (hasActiveB2b) return;
                    const subToBuy = selectedSub;
                    setSelectedSub(null);
                    if (!isLoggedIn) {
                      window.location.assign("/login");
                      return;
                    }
                    setBuyModalConfig({ subscription: subToBuy, type: "b2b" });
                  }}
                  className={cn(
                    "flex-1 flex items-center justify-center gap-1.5 rounded-xl border px-4 py-2.5 text-xs font-bold transition",
                    hasActiveB2b
                      ? "border-muted bg-muted/20 text-muted-foreground opacity-60 cursor-not-allowed"
                      : "border-primary/30 bg-primary/5 text-primary hover:bg-primary/10 cursor-pointer",
                  )}
                >
                  <Building2 className="size-4" />
                  {hasActiveB2b ? "لديك اشتراك B2B" : "اشتراك B2B"}
                </button>
              ) : null}
            </div>
          </div>
        </div>
      )}

      {/* ── Buy Subscription Checkout Modal (with Coupons) ── */}
      {buyModalConfig && (
        <BuySubscriptionModal
          subscription={buyModalConfig.subscription}
          type={buyModalConfig.type}
          open={Boolean(buyModalConfig)}
          onClose={() => setBuyModalConfig(null)}
        />
      )}
    </div>
  );
}
