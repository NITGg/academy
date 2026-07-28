"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { BookOpen, CheckCircle2, Loader2 } from "lucide-react";
import { useLocale } from "next-intl";
import { parseMlang } from "@/lib/mlang";
import { cn } from "@/lib/utils";
import type { EnrolledCourse } from "../server";
import { enrollPurchased } from "../actions";

interface MyCourseCardProps {
  course: EnrolledCourse;
  className?: string;
}

function moodlePublicUrl(url: string): string {
  return url.replace("/webservice/pluginfile.php", "/pluginfile.php");
}

export function MyCourseCard({ course, className }: MyCourseCardProps) {
  const locale = useLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const router = useRouter();

  const [enrolling, startEnroll] = useTransition();
  const [enrolled, setEnrolled] = useState(!course.isPurchasedNotEnrolled);
  const [error, setError] = useState<string | null>(null);

  const fullname = parseMlang(course.fullname ?? "", lang);
  const imageUrl = course.overviewfiles?.[0]?.fileurl
    ? moodlePublicUrl(course.overviewfiles[0].fileurl)
    : null;
  const progress = typeof course.progress === "number" ? Math.round(course.progress) : 0;
  const completed = course.completed === true || progress >= 100;

  const isPurchasedNotEnrolled = !enrolled && course.isPurchasedNotEnrolled;

  const handleEnrollPurchased = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setError(null);
    startEnroll(async () => {
      const res = await enrollPurchased(course.id);
      if (res.needsAuth) router.push(`/login?from=/courses/${course.id}`);
      else if (res.error) setError(res.error);
      else setEnrolled(true);
    });
  };

  return (
    <Link
      href={`/courses/${course.id}`}
      className={cn(
        "group flex h-full flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
        className
      )}
    >
      {/* Cover image */}
      <div className="relative aspect-video w-full overflow-hidden bg-muted">
        {imageUrl ? (
          <Image
            src={imageUrl}
            alt={fullname}
            fill
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
            unoptimized
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-primary/10">
            <BookOpen className="size-10 text-primary/40" />
          </div>
        )}

        {/* Completion badge */}
        {enrolled && completed && (
          <span className="absolute start-2 top-2 flex items-center gap-1 rounded-lg bg-emerald-500 px-2.5 py-1 text-xs font-bold text-white shadow-sm">
            <CheckCircle2 className="size-3" />
            مكتمل
          </span>
        )}

        {/* Purchased badge */}
        {isPurchasedNotEnrolled && (
          <span className="absolute start-2 top-2 rounded-lg bg-amber-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm">
            تم الشراء
          </span>
        )}
      </div>

      {/* Body */}
      <div className="flex flex-1 flex-col gap-3 p-4">
        <h3 className="line-clamp-2 text-sm font-bold leading-snug text-primary">
          {fullname}
        </h3>

        {/* Progress or Enroll CTA */}
        <div className="mt-auto space-y-1.5">
          {isPurchasedNotEnrolled ? (
            <div className="space-y-1">
              <button
                type="button"
                onClick={handleEnrollPurchased}
                disabled={enrolling}
                className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-amber-600 py-2 text-xs font-bold text-white transition hover:bg-amber-700 disabled:opacity-60"
              >
                {enrolling ? <Loader2 className="size-4 animate-spin" /> : <BookOpen className="size-4" />}
                {enrolling ? "جارٍ الانضمام..." : "انضمام للكورس"}
              </button>
              {error && <p className="text-center text-[10px] text-red-600">{error}</p>}
            </div>
          ) : (
            <>
              <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                  className="h-full rounded-full bg-emerald-500 transition-all"
                  style={{ width: `${progress}%` }}
                />
              </div>
              <div className="flex items-center justify-between text-[11px]">
                {completed ? (
                  <div className="flex items-center gap-1 text-emerald-600">
                    <CheckCircle2 className="size-3.5" />
                    <span className="font-semibold">اكتمل 100%</span>
                  </div>
                ) : (
                  <span className="text-muted-foreground">متابعة التعلم</span>
                )}
                <span className="font-bold text-foreground">{progress}%</span>
              </div>
            </>
          )}
        </div>
      </div>
    </Link>
  );
}
