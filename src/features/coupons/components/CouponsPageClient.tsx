"use client";

import { useState } from "react";
import { useLocale } from "next-intl";
import {
  Tag,
  Copy,
  Check,
  Calendar,
  Search,
  Ticket,
  Sparkles,
} from "lucide-react";
import { toast } from "sonner";
import type { AvailableCoupon } from "../types";

interface CouponsPageClientProps {
  coupons: AvailableCoupon[];
}

export function CouponsPageClient({ coupons }: CouponsPageClientProps) {
  const locale = useLocale();
  const isRtl = locale === "ar";
  const [copiedCode, setCopiedCode] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState("");

  const handleCopyCode = async (code: string) => {
    try {
      await navigator.clipboard.writeText(code);
      setCopiedCode(code);
      toast.success(
        isRtl ? "تم نسخ كود الكوبون بنجاح" : "Coupon code copied to clipboard",
      );
      setTimeout(() => setCopiedCode(null), 2500);
    } catch {
      toast.error(isRtl ? "فشل نسخ الكود" : "Failed to copy code");
    }
  };

  const filteredCoupons = coupons.filter((coupon) => {
    if (!searchQuery.trim()) return true;
    const query = searchQuery.toLowerCase();
    const codeMatch = coupon.code.toLowerCase().includes(query);
    const targetMatch = coupon.applies_to?.some((target) =>
      target.label.toLowerCase().includes(query),
    );
    return codeMatch || targetMatch;
  });

  const formatDate = (timestamp?: number) => {
    if (!timestamp) return null;
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString(isRtl ? "ar-EG" : "en-US", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    });
  };

  return (
    <div className="space-y-6">
      {/* Subheader hint */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <p className="text-sm text-muted-foreground">
          {isRtl
            ? "انسخ الكود واستخدمه عند الدفع"
            : "Copy the code and use it at checkout"}
        </p>
      </div>

      {/* Coupons grid or empty state */}
      {filteredCoupons.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed p-12 text-center bg-card/50">
          <div className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary mb-4">
            <Ticket className="size-7" />
          </div>
          <h3 className="text-lg font-semibold mb-1">
            {searchQuery
              ? isRtl
                ? "لا توجد نتائج بحث"
                : "No matching coupons found"
              : isRtl
                ? "لا توجد كوبونات متاحة حالياً"
                : "No coupons available right now"}
          </h3>
          <p className="text-sm text-muted-foreground max-w-md">
            {searchQuery
              ? isRtl
                ? "جرب البحث برمز كوبون آخر"
                : "Try searching with a different coupon code"
              : isRtl
                ? "تابعنا للحصول على أحدث العروض والخصومات الحصرية"
                : "Check back later for exclusive discounts and special offers"}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          {filteredCoupons.map((coupon, idx) => {
            const isCopied = copiedCode === coupon.code;

            // Compute discount title
            const isPercent = coupon.discount_type === "percent";
            const discountLabel = isPercent
              ? isRtl
                ? `خصم ${coupon.discount_value}%`
                : `${coupon.discount_value}% OFF`
              : isRtl
                ? `خصم ${coupon.discount_value} جنيه`
                : `${coupon.discount_value} EGP OFF`;

            // Expiry date
            const expiryDateStr = formatDate(coupon.enddate);

            // Remaining usage
            let remainingUses: number | null = null;
            if (typeof coupon.usage_limit === "number") {
              const used = coupon.usage_count ?? 0;
              remainingUses = Math.max(0, coupon.usage_limit - used);
            }

            return (
              <div
                key={coupon.code || idx}
                className="group relative flex flex-col justify-between rounded-2xl border border-border/80 bg-card p-5 shadow-sm transition-all duration-200 hover:shadow-md hover:border-primary/40"
              >
                <div className="space-y-4">
                  {/* Top Discount Header */}
                  <div className="flex items-start justify-between gap-3">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <h3 className="text-xl font-bold text-foreground">
                          {discountLabel}
                        </h3>
                        <Tag className="size-5 text-primary shrink-0" />
                      </div>
                      {coupon.max_discount && coupon.max_discount > 0 ? (
                        <p className="text-xs text-muted-foreground">
                          {isRtl
                            ? `حتى ${coupon.max_discount} جنيه`
                            : `Up to ${coupon.max_discount} EGP`}
                        </p>
                      ) : null}
                    </div>

                    {coupon.usage_type === "once_per_user" && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-medium text-amber-600 dark:text-amber-400 border border-amber-500/20">
                        <Sparkles className="size-3" />
                        {isRtl ? "مرة واحدة" : "Once per user"}
                      </span>
                    )}
                  </div>

                  {/* Stylized Copy Box */}
                  <div className="flex items-center justify-between gap-3 rounded-xl border border-primary/20 bg-primary/5 p-3 dark:bg-primary/10">
                    <span className="font-mono text-base font-bold tracking-wider text-primary dir-ltr">
                      {coupon.code}
                    </span>

                    <button
                      type="button"
                      onClick={() => handleCopyCode(coupon.code)}
                      className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all active:scale-95 ${
                        isCopied
                          ? "bg-emerald-600 text-white shadow-sm"
                          : "bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm"
                      }`}
                    >
                      {isCopied ? (
                        <>
                          <Check className="size-3.5" />
                          <span>{isRtl ? "تم النسخ!" : "Copied!"}</span>
                        </>
                      ) : (
                        <>
                          <Copy className="size-3.5" />
                          <span>{isRtl ? "اضغط للنسخ" : "Click to copy"}</span>
                        </>
                      )}
                    </button>
                  </div>

                  {/* Applies to section ("يستخدم على") */}
                  <div className="space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground block">
                      {isRtl ? "يستخدم على" : "Applies to"}
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                      {coupon.applies_to && coupon.applies_to.length > 0 ? (
                        coupon.applies_to.map((target, tIdx) => (
                          <span
                            key={tIdx}
                            className="inline-flex items-center rounded-lg bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground"
                          >
                            {target.label}
                          </span>
                        ))
                      ) : (
                        <>
                          <span className="inline-flex items-center rounded-lg bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground">
                            {isRtl ? "كل المقررات" : "All Courses"}
                          </span>
                          <span className="inline-flex items-center rounded-lg bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground">
                            {isRtl ? "كل الباقات" : "All Packages"}
                          </span>
                          <span className="inline-flex items-center rounded-lg bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground">
                            {isRtl ? "كل الاشتراكات" : "All Subscriptions"}
                          </span>
                        </>
                      )}
                    </div>
                  </div>
                </div>

                {/* Footer metadata: validity & remaining count */}
                <div className="mt-5 pt-3 border-t border-border/60 flex items-center justify-between text-xs text-muted-foreground gap-2">
                  {expiryDateStr ? (
                    <div className="flex items-center gap-1.5">
                      <Calendar className="size-3.5 shrink-0" />
                      <span>
                        {isRtl
                          ? `صالح حتى ${expiryDateStr}`
                          : `Valid until ${expiryDateStr}`}
                      </span>
                    </div>
                  ) : (
                    <div />
                  )}

                  {remainingUses !== null && (
                    <span className="font-medium text-foreground/80">
                      {isRtl
                        ? `${remainingUses} استخدامات متبقية`
                        : `${remainingUses} uses remaining`}
                    </span>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
