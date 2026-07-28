"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { BadgeCheck, Loader2, CheckCircle2 } from "lucide-react";
import { enrollViaSubscription } from "../actions";

interface SubscriptionEnrollButtonProps {
  courseId: number;
  locale: string;
}

/** Free on-demand enrolment for a course covered by the user's active subscription. */
export function SubscriptionEnrollButton({ courseId, locale }: SubscriptionEnrollButtonProps) {
  const [isPending, startTransition] = useTransition();
  const [enrolled, setEnrolled] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();
  const isAr = locale === "ar";

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
      const res = await enrollViaSubscription(courseId);
      if (res.needsAuth) router.push(`/login?from=/courses/${courseId}`);
      else if (res.error) setError(res.error);
      else setEnrolled(true);
    });
  };

  return (
    <div className="space-y-2">
      <button
        onClick={handleClick}
        disabled={isPending}
        className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-60"
      >
        {isPending ? <Loader2 className="size-4 animate-spin" /> : <BadgeCheck className="size-4" />}
        {isPending
          ? isAr ? "جارٍ الانضمام..." : "Joining..."
          : isAr ? "انضمام للكورس" : "Join Course"}
      </button>
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}
