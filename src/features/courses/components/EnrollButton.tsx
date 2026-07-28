"use client";

import { useTransition, useState } from "react";
import { useRouter } from "next/navigation";
import { BookOpen, ShoppingCart, Loader2, CheckCircle2, Clock } from "lucide-react";
import { enrollFree, startCourseCheckout, enrollPurchased } from "../actions";

interface EnrollButtonProps {
  courseId: number;
  isFree?: boolean;
  isPurchased?: boolean;
  price?: number;
  currency?: string;
  hasPendingPayment?: boolean;
  locale: string;
}

export function EnrollButton({
  courseId,
  isFree = false,
  isPurchased = false,
  price,
  currency = "جنيه",
  hasPendingPayment,
  locale,
}: EnrollButtonProps) {
  const [isPending, startTransition] = useTransition();
  const [enrolled, setEnrolled] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();

  const isAr = locale === "ar";

  if (hasPendingPayment) {
    return (
      <div className="inline-flex items-center gap-2 rounded-xl border border-amber-400/40 bg-amber-500/10 px-5 py-2.5 text-sm font-semibold text-amber-600">
        <Clock className="size-4" />
        {isAr ? "في انتظار تأكيد الدفع" : "Payment pending confirmation"}
      </div>
    );
  }

  if (enrolled) {
    return (
      <div className="inline-flex items-center gap-2 rounded-xl bg-emerald-500/10 px-5 py-2.5 text-sm font-semibold text-emerald-600">
        <CheckCircle2 className="size-4" />
        {isAr ? "تم التسجيل بنجاح!" : "Enrolled successfully!"}
      </div>
    );
  }

  const handleClick = () => {
    setError(null);
    startTransition(async () => {
      if (isPurchased) {
        const result = await enrollPurchased(courseId);
        if (result.needsAuth) {
          router.push(`/login?from=/courses/${courseId}`);
        } else if (result.error) {
          setError(result.error);
        } else {
          setEnrolled(true);
        }
      } else if (isFree) {
        const result = await enrollFree(courseId);
        if (result.needsAuth) {
          router.push(`/login?from=/courses/${courseId}`);
        } else if (result.error) {
          setError(result.error);
        } else {
          setEnrolled(true);
        }
      } else {
        const result = await startCourseCheckout(courseId);
        if (result.needsAuth) {
          router.push(`/login?from=/courses/${courseId}`);
        } else if (result.error) {
          setError(result.error);
        } else if (result.checkoutUrl) {
          window.location.assign(result.checkoutUrl);
        }
      }
    });
  };

  return (
    <div className="space-y-2">
      <button
        onClick={handleClick}
        disabled={isPending}
        className={`inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold transition hover:opacity-90 disabled:opacity-60 ${
          isPurchased
            ? "bg-amber-600 hover:bg-amber-700 text-white"
            : "bg-primary text-primary-foreground"
        }`}
      >
        {isPending ? (
          <Loader2 className="size-4 animate-spin" />
        ) : isPurchased || isFree ? (
          <BookOpen className="size-4" />
        ) : (
          <ShoppingCart className="size-4" />
        )}
        {isPending
          ? (isAr ? "جارٍ الانضمام..." : "Joining...")
          : isPurchased || isFree
            ? (isAr ? "انضمام للكورس" : "Join Course")
            : (isAr
                ? `اشترِ مقابل ${price} ${currency}`
                : `Buy for ${price} ${currency}`)}
      </button>

      {error && (
        <p className="text-xs text-destructive">{error}</p>
      )}
    </div>
  );
}
