"use client";

import { useState } from "react";
import { Building2, X, Loader2, Check, Settings2 } from "lucide-react";
import { Pagination } from "@/components/ui/Pagination";
import { cn } from "@/lib/utils";
import { startSubscriptionCheckout } from "../actions";
import type { AvailableSubscription } from "../types";

interface SubscriptionCatalogProps {
  subscriptions: AvailableSubscription[];
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
  isLoggedIn,
  hasActiveSubscription,
  ownedB2bSubIds,
  onManageB2b,
  pageSize = 6,
}: SubscriptionCatalogProps) {
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedSub, setSelectedSub] = useState<AvailableSubscription | null>(null);
  const [b2bSub, setB2bSub] = useState<AvailableSubscription | null>(null);
  const [selectedSeats, setSelectedSeats] = useState<number>(10);
  const [loadingId, setLoadingId] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  async function handleSubscriptionBuy(
    subId: number,
    type: "normal" | "b2b" = "normal",
    seats?: number,
  ) {
    if (!isLoggedIn) {
      window.location.assign("/login");
      return;
    }
    setLoadingId(`sub-${subId}-${type}`);
    setActionError(null);
    const res = await startSubscriptionCheckout({ subscriptionId: subId, type, seats });
    setLoadingId(null);

    if (res.needsAuth) {
      window.location.assign("/login");
    } else if (res.checkoutUrl) {
      window.location.assign(res.checkoutUrl);
    } else {
      setActionError(res.error || "تعذّر بدء عملية الدفع للاشتراك");
    }
  }

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
      {actionError && (
        <div className="flex items-center justify-between rounded-2xl bg-destructive/10 p-4 text-xs font-semibold text-destructive">
          <span>{actionError}</span>
          <button onClick={() => setActionError(null)}>
            <X className="size-4" />
          </button>
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {currentSubscriptions.map((sub) => {
          const ownsB2b = ownedB2bSubIds.has(sub.id);
          const b2bEnabled = Number(sub.b2b_enabled) === 1;
          const showB2bButton = ownsB2b || b2bEnabled; // not every plan offers B2B
          const isThisSubActive = false; // active-plan state handled by hasActiveSubscription
          const isUserSubActive = hasActiveSubscription;

          return (
            <div
              key={sub.id}
              onClick={() => setSelectedSub(sub)}
              className="cursor-pointer space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm transition hover:border-primary/50 hover:shadow-md"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="flex flex-wrap items-center gap-1.5">
                  <span className="rounded-lg bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 whitespace-nowrap">
                    {sub.duration_days} يوم
                  </span>
                  {ownsB2b ? (
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
                  disabled={isUserSubActive || loadingId === `sub-${sub.id}-normal`}
                  onClick={() => handleSubscriptionBuy(sub.id, "normal")}
                  className={cn(
                    "flex items-center justify-center gap-1 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50",
                    showB2bButton ? "flex-1" : "w-full",
                  )}
                >
                  {loadingId === `sub-${sub.id}-normal` ? (
                    <Loader2 className="size-3.5 animate-spin" />
                  ) : isThisSubActive ? (
                    "مشترك"
                  ) : isUserSubActive ? (
                    "لديك اشتراك"
                  ) : (
                    "اشتراك فردي"
                  )}
                </button>

                {/* B2B button — only for plans that allow B2B; becomes "manage" when already owned */}
                {ownsB2b ? (
                  <button
                    type="button"
                    onClick={() => onManageB2b(sub.id)}
                    className="flex-1 flex items-center justify-center gap-1 rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-3 py-2 text-xs font-bold text-emerald-600 transition hover:bg-emerald-500/10"
                  >
                    <Settings2 className="size-3.5" />
                    إدارة اشتراك B2B
                  </button>
                ) : b2bEnabled ? (
                  <button
                    type="button"
                    onClick={() => {
                      setB2bSub(sub);
                      if (sub.seat_options && sub.seat_options.length > 0) {
                        setSelectedSeats(sub.seat_options[0].seats);
                      }
                    }}
                    className="flex-1 flex items-center justify-center gap-1 rounded-xl border border-primary/30 bg-primary/5 px-3 py-2 text-xs font-bold text-primary transition hover:bg-primary/10"
                  >
                    <Building2 className="size-3.5" />
                    اشتراك B2B
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
                disabled={hasActiveSubscription || loadingId === `sub-${selectedSub.id}-normal`}
                onClick={() => handleSubscriptionBuy(selectedSub.id, "normal")}
                className={cn(
                  "flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50",
                  ownedB2bSubIds.has(selectedSub.id) || Number(selectedSub.b2b_enabled) === 1
                    ? "flex-1"
                    : "w-full",
                )}
              >
                {loadingId === `sub-${selectedSub.id}-normal` ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : hasActiveSubscription ? (
                  "لديك اشتراك نشط"
                ) : (
                  "اشتراك فردي"
                )}
              </button>

              {ownedB2bSubIds.has(selectedSub.id) ? (
                <button
                  type="button"
                  onClick={() => {
                    const id = selectedSub.id;
                    setSelectedSub(null);
                    onManageB2b(id);
                  }}
                  className="flex-1 flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-4 py-2.5 text-xs font-bold text-emerald-600 transition hover:bg-emerald-500/10"
                >
                  <Settings2 className="size-4" />
                  إدارة اشتراك B2B
                </button>
              ) : Number(selectedSub.b2b_enabled) === 1 ? (
                <button
                  type="button"
                  onClick={() => {
                    setB2bSub(selectedSub);
                    if (selectedSub.seat_options && selectedSub.seat_options.length > 0) {
                      setSelectedSeats(selectedSub.seat_options[0].seats);
                    }
                    setSelectedSub(null);
                  }}
                  className="flex-1 flex items-center justify-center gap-1.5 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5 text-xs font-bold text-primary transition hover:bg-primary/10"
                >
                  <Building2 className="size-4" />
                  اشتراك B2B
                </button>
              ) : null}
            </div>
          </div>
        </div>
      )}

      {/* ── B2B Seats Selection Modal ── */}
      {b2bSub && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl border border-border space-y-4">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <div className="flex items-center gap-2">
                <Building2 className="size-5 text-primary" />
                <h3 className="text-base font-bold text-foreground">اشتراك شركات (B2B) - {b2bSub.name}</h3>
              </div>
              <button onClick={() => setB2bSub(null)} className="text-muted-foreground hover:text-foreground">
                <X className="size-5" />
              </button>
            </div>

            <p className="text-xs text-muted-foreground">
              اختر عدد المقاعد المطلوبة لشركتك أو فريقك للاستفادة من خصومات الحجم:
            </p>

            {b2bSub.seat_options && b2bSub.seat_options.length > 0 ? (
              <div className="grid gap-2">
                {b2bSub.seat_options.map((option) => {
                  const isSelected = selectedSeats === option.seats;
                  return (
                    <button
                      key={option.seats}
                      type="button"
                      onClick={() => setSelectedSeats(option.seats)}
                      className={`flex items-center justify-between rounded-xl border p-3 text-xs transition ${
                        isSelected
                          ? "border-primary bg-primary/10 text-primary font-bold"
                          : "border-border bg-background text-foreground hover:bg-muted"
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        <UsersIcon className="size-4" />
                        <span>{option.seats} مقعد (حساب)</span>
                      </div>
                      {option.discount_percent > 0 && (
                        <span className="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold text-emerald-600">
                          خصم {option.discount_percent}%
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            ) : (
              <div className="space-y-2">
                <label className="text-xs font-bold text-foreground">عدد المقاعد:</label>
                <input
                  type="number"
                  min={2}
                  max={500}
                  value={selectedSeats}
                  onChange={(e) => setSelectedSeats(Math.max(1, parseInt(e.target.value) || 1))}
                  className="w-full rounded-xl border border-border bg-background p-2.5 text-xs text-foreground outline-none focus:ring-1 focus:ring-primary"
                />
              </div>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setB2bSub(null)}
                className="rounded-xl px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted"
              >
                إلغاء
              </button>
              <button
                type="button"
                disabled={loadingId === `sub-${b2bSub.id}-b2b`}
                onClick={() => handleSubscriptionBuy(b2bSub.id, "b2b", selectedSeats)}
                className="flex items-center gap-2 rounded-xl bg-primary px-5 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
              >
                {loadingId === `sub-${b2bSub.id}-b2b` ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : (
                  `إتمام الدفع (${selectedSeats} مقعد)`
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
