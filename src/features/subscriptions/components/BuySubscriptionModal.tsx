"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { X, Loader2, Tag, CheckCircle2, ShoppingCart, Building2, Users } from "lucide-react";
import {
  previewSubscriptionDiscount,
  startSubscriptionCheckout,
  type SubscriptionDiscountPreview,
} from "../actions";
import type { AvailableSubscription } from "../types";

interface BuySubscriptionModalProps {
  subscription: AvailableSubscription;
  type?: "normal" | "b2b";
  initialSeats?: number;
  open: boolean;
  onClose: () => void;
}

export function BuySubscriptionModal({
  subscription,
  type = "normal",
  initialSeats = 10,
  open,
  onClose,
}: BuySubscriptionModalProps) {
  const router = useRouter();
  const [coupon, setCoupon] = useState("");
  const [seats, setSeats] = useState(initialSeats);
  const [preview, setPreview] = useState<SubscriptionDiscountPreview | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isPaying, startPay] = useTransition();

  const [prevOpen, setPrevOpen] = useState(open);
  const [prevSubId, setPrevSubId] = useState(subscription.id);

  if (open !== prevOpen || subscription.id !== prevSubId) {
    setPrevOpen(open);
    setPrevSubId(subscription.id);
    if (open) {
      if (subscription.seat_options && subscription.seat_options.length > 0) {
        setSeats(subscription.seat_options[0].seats);
      }
      setCoupon("");
      setError(null);
      setPreview(null);
      setPreviewing(true);
    }
  }

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    previewSubscriptionDiscount({ subscriptionId: subscription.id, couponCode: "" })
      .then((res) => {
        if (cancelled) return;
        if (res.data) setPreview(res.data);
      })
      .finally(() => !cancelled && setPreviewing(false));
    return () => {
      cancelled = true;
    };
  }, [open, subscription.id]);

  if (!open) return null;

  const checkCoupon = () => {
    setError(null);
    setPreviewing(true);
    previewSubscriptionDiscount({ subscriptionId: subscription.id, couponCode: coupon })
      .then((res) => {
        if (res.needsAuth) {
          router.push("/login");
          return;
        }
        if (res.error) {
          setError(res.error);
          return;
        }
        if (res.data) setPreview(res.data);
      })
      .finally(() => setPreviewing(false));
  };

  const handlePay = () => {
    startPay(async () => {
      const res = await startSubscriptionCheckout({
        subscriptionId: subscription.id,
        type,
        seats: type === "b2b" ? seats : undefined,
        couponCode: coupon,
      });
      if (res.needsAuth) {
        router.push("/login");
      } else if (res.error) {
        setError(res.error);
      } else if (res.checkoutUrl) {
        window.location.assign(res.checkoutUrl);
      }
    });
  };

  const isB2b = type === "b2b";
  const basePrice = Number(subscription.offer ? subscription.offer.final : subscription.price);
  const unitPrice = Number(preview?.final ?? basePrice);
  const finalPrice = isB2b ? unitPrice * seats : unitPrice;
  const currency = "جنيه";
  const couponApplied = (preview?.couponDiscount ?? 0) > 0;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
      onClick={onClose}
    >
      <div
        className="w-full max-w-md rounded-t-3xl bg-card p-6 shadow-xl sm:rounded-3xl border border-border space-y-4 max-h-[90vh] overflow-y-auto"
        onClick={(e) => e.stopPropagation()}
        dir="rtl"
      >
        {/* Header */}
        <div className="flex items-start justify-between gap-3">
          <button
            onClick={onClose}
            className="rounded-full p-1 text-muted-foreground hover:bg-muted"
            aria-label="إغلاق"
          >
            <X className="size-5" />
          </button>
          <div className="text-end">
            <h3 className="text-base font-bold text-foreground">
              {isB2b ? "اشتراك شركات (B2B)" : "شراء الاشتراك"}
            </h3>
            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{subscription.name}</p>
          </div>
        </div>

        {/* Seats configuration for B2B */}
        {isB2b && (
          <div className="space-y-2 rounded-2xl border border-border bg-muted/20 p-3">
            <label className="text-xs font-bold text-foreground flex items-center justify-end gap-1.5">
              عدد المقاعد المطلوب <Building2 className="size-4 text-primary" />
            </label>
            {subscription.seat_options && subscription.seat_options.length > 0 ? (
              <div className="grid gap-2">
                {subscription.seat_options.map((opt) => (
                  <button
                    key={opt.seats}
                    type="button"
                    onClick={() => setSeats(opt.seats)}
                    className={`flex items-center justify-between rounded-xl border p-2.5 text-xs transition ${
                      seats === opt.seats
                        ? "border-primary bg-primary/10 text-primary font-bold"
                        : "border-border bg-background text-foreground hover:bg-muted"
                    }`}
                  >
                    <div className="flex items-center gap-1.5">
                      <Users className="size-3.5" />
                      <span>{opt.seats} مقعد</span>
                    </div>
                    {opt.discount_percent > 0 && (
                      <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600">
                        خصم {opt.discount_percent}%
                      </span>
                    )}
                  </button>
                ))}
              </div>
            ) : (
              <input
                type="number"
                min={2}
                max={500}
                value={seats}
                onChange={(e) => setSeats(Math.max(1, parseInt(e.target.value) || 1))}
                className="w-full rounded-xl border border-border bg-background p-2.5 text-end text-xs text-foreground outline-none focus:ring-1 focus:ring-primary"
              />
            )}
          </div>
        )}

        {/* Price summary */}
        <div className="rounded-2xl bg-muted/50 p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-baseline gap-2">
              <span className="text-xl font-extrabold text-foreground">
                {finalPrice} {currency}
              </span>
            </div>
            <span className="text-xs font-semibold text-muted-foreground">
              {isB2b ? `الإجمالي (${seats} مقعد)` : "الإجمالي"}
            </span>
          </div>

          {preview && preview.offerDiscount > 0 && (
            <div className="mt-2 flex items-center justify-between text-[11px] text-emerald-600">
              <span>−{preview.offerDiscount} {currency}</span>
              <span>{preview.offerName || "خصم العرض"}</span>
            </div>
          )}
          {couponApplied && (
            <div className="mt-1 flex items-center justify-between text-[11px] text-emerald-600">
              <span>−{preview!.couponDiscount} {currency}</span>
              <span className="flex items-center gap-1">
                <CheckCircle2 className="size-3.5" /> خصم الكوبون
              </span>
            </div>
          )}
        </div>

        {/* Coupon field */}
        <div className="space-y-1.5">
          <label className="flex items-center justify-end gap-1.5 text-xs font-bold text-foreground">
            كوبون الخصم <Tag className="size-3.5" />
          </label>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={checkCoupon}
              disabled={previewing || !coupon.trim()}
              className="shrink-0 rounded-xl border border-border px-4 text-xs font-semibold text-foreground transition hover:bg-muted disabled:opacity-50"
            >
              تطبيق
            </button>
            <input
              type="text"
              value={coupon}
              onChange={(e) => setCoupon(e.target.value)}
              onBlur={() => coupon.trim() && checkCoupon()}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  e.preventDefault();
                  checkCoupon();
                }
              }}
              placeholder="أدخل الكود"
              className="w-full rounded-xl border border-input bg-background p-2.5 text-end text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none"
            />
          </div>
          {preview?.couponError && (
            <p className="text-end text-[11px] text-red-600">{preview.couponError}</p>
          )}
          {couponApplied && !preview?.couponError && (
            <p className="text-end text-[11px] text-emerald-600">تم تطبيق الكوبون بنجاح</p>
          )}
        </div>

        {error && (
          <p className="rounded-lg bg-red-50 px-3 py-2 text-end text-[11px] text-red-600 dark:bg-red-900/20">
            {error}
          </p>
        )}

        {/* Pay */}
        <button
          onClick={handlePay}
          disabled={isPaying || previewing}
          className="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20 transition hover:opacity-90 disabled:opacity-60"
        >
          {isPaying ? <Loader2 className="size-4 animate-spin" /> : <ShoppingCart className="size-4" />}
          {isPaying ? "جارٍ التحويل للدفع…" : `ادفع ${finalPrice} ${currency}`}
        </button>
        <p className="text-center text-[10px] text-muted-foreground">
          سيتم تحويلك إلى بوابة الدفع الآمنة (Kashier)
        </p>
      </div>
    </div>
  );
}
