"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { X, Loader2, Tag, CheckCircle2, ShoppingCart } from "lucide-react";
import {
  previewPackageDiscount,
  startPackageCheckout,
  type PackageDiscountPreview,
} from "../actions";

interface BuyPackageModalProps {
  packageId: number;
  packageName: string;
  basePrice: number;
  currency?: string;
  open: boolean;
  onClose: () => void;
}

export function BuyPackageModal({
  packageId,
  packageName,
  basePrice,
  currency = "جنيه",
  open,
  onClose,
}: BuyPackageModalProps) {
  const router = useRouter();
  const [coupon, setCoupon] = useState("");
  const [preview, setPreview] = useState<PackageDiscountPreview | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isPaying, startPay] = useTransition();

  const [prevOpen, setPrevOpen] = useState(open);
  const [prevPackageId, setPrevPackageId] = useState(packageId);

  if (open !== prevOpen || packageId !== prevPackageId) {
    setPrevOpen(open);
    setPrevPackageId(packageId);
    if (open) {
      setCoupon("");
      setError(null);
      setPreview(null);
      setPreviewing(true);
    }
  }

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    previewPackageDiscount(packageId, "")
      .then((res) => {
        if (cancelled) return;
        if (res.data) setPreview(res.data);
      })
      .finally(() => !cancelled && setPreviewing(false));
    return () => {
      cancelled = true;
    };
  }, [open, packageId]);

  if (!open) return null;

  const checkCoupon = () => {
    setError(null);
    setPreviewing(true);
    previewPackageDiscount(packageId, coupon)
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
      const res = await startPackageCheckout(packageId, coupon);
      if (res.needsAuth) {
        router.push("/login");
      } else if (res.error) {
        setError(res.error);
      } else if (res.checkoutUrl) {
        window.location.assign(res.checkoutUrl);
      }
    });
  };

  const finalPrice = preview?.final ?? basePrice;
  const strikePrice = preview && preview.original !== preview.final ? preview.original : undefined;
  const couponApplied = (preview?.couponDiscount ?? 0) > 0;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
      onClick={onClose}
    >
      <div
        className="w-full max-w-md rounded-t-3xl bg-card p-6 shadow-xl sm:rounded-3xl border border-border space-y-4"
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
            <h3 className="text-base font-bold text-foreground">شراء الباقة</h3>
            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{packageName}</p>
          </div>
        </div>

        {/* Price summary */}
        <div className="rounded-2xl bg-muted/50 p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-baseline gap-2">
              {strikePrice != null && (
                <span className="text-sm text-muted-foreground line-through">
                  {strikePrice} {currency}
                </span>
              )}
              <span className="text-xl font-extrabold text-foreground">
                {finalPrice} {currency}
              </span>
            </div>
            <span className="text-xs font-semibold text-muted-foreground">الإجمالي</span>
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
