"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { BookOpen, Users, ShoppingCart, Loader2, PlayCircle, BadgeCheck } from "lucide-react";
import { useLocale } from "next-intl";
import { parseMlang } from "@/lib/mlang";
import type { Course } from "../types";
import { enrollFree, enrollViaSubscription, enrollPurchased } from "../actions";
import { BuyCourseModal } from "./BuyCourseModal";

export type CoverageType = "normal_sub" | "b2b_sub" | "program";

interface CourseCardProps {
  course: Course;
  /** Coverage type when unlocked by subscription or program (free on-demand enrol). */
  coverageType?: CoverageType | null;
  /** Backward compatibility flag for subscription coverage */
  coveredBySubscription?: boolean;
  /** Prioritize image loading for LCP */
  priority?: boolean;
}

export function CourseCard({ course, coverageType, coveredBySubscription, priority }: CourseCardProps) {
  const resolvedCoverage = coverageType ?? (coveredBySubscription ? "normal_sub" : null);
  const locale = useLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const router = useRouter();

  const [modalOpen, setModalOpen] = useState(false);
  const [enrolling, startEnroll] = useTransition();
  const [enrolled, setEnrolled] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fullname = parseMlang(course.fullname ?? "", lang);
  const shortname = parseMlang(course.shortname ?? "", lang);
  const categoryname = parseMlang(course.categoryname ?? "", lang);
  const currency = course.currency ?? "جنيه";

  const isFree = course.isFree ?? !course.price;
  const isEnrolled = enrolled || course.isEnrolled;
  const isPurchasedNotEnrolled = !isEnrolled && course.isPurchased;
  const hasDiscount =
    !isFree && course.originalPrice != null && course.originalPrice > (course.price ?? 0);

  const priceLabel = isFree ? "مجاني" : `${course.price} ${currency}`;

  const handleEnrollFree = () => {
    setError(null);
    startEnroll(async () => {
      const res = await enrollFree(course.id);
      if (res.needsAuth) router.push(`/login?from=/courses/${course.id}`);
      else if (res.error) setError(res.error);
      else setEnrolled(true);
    });
  };

  const handleEnrollViaSubscription = () => {
    setError(null);
    startEnroll(async () => {
      const res = await enrollViaSubscription(course.id);
      if (res.needsAuth) router.push(`/login?from=/courses/${course.id}`);
      else if (res.error) setError(res.error);
      else setEnrolled(true);
    });
  };

  const handleEnrollPurchased = () => {
    setError(null);
    startEnroll(async () => {
      const res = await enrollPurchased(course.id);
      if (res.needsAuth) router.push(`/login?from=/courses/${course.id}`);
      else if (res.error) setError(res.error);
      else setEnrolled(true);
    });
  };

  // A paid course unlocked by subscription or program: offer free on-demand enrol instead of "buy".
  const canEnrollViaSub = Boolean(resolvedCoverage) && !isFree && !isEnrolled && !isPurchasedNotEnrolled;

  return (
    <div className="group flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-shadow hover:shadow-md">
      {/* Thumbnail → detail */}
      <Link href={`/courses/${course.id}`} className="relative block aspect-video w-full overflow-hidden bg-muted">
        {course.courseimage ? (
          <Image
            src={course.courseimage}
            alt={fullname}
            fill
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-primary/10">
            <BookOpen className="size-10 text-primary/40" />
          </div>
        )}

        {/* Price badge */}
        <span
          className={`absolute end-2 top-2 rounded-lg px-2.5 py-1 text-xs font-bold shadow-sm ${
            isFree ? "bg-emerald-500 text-white" : "bg-background/90 text-foreground backdrop-blur-sm"
          }`}
        >
          {priceLabel}
        </span>

        {/* Offer / discount badge */}
        {hasDiscount && (
          <span className="absolute end-2 top-10 rounded-lg bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm">
            {course.discountPercentage ? `-${course.discountPercentage}%` : "عرض"}
          </span>
        )}

        {/* Enrolled badge */}
        {isEnrolled && (
          <span className="absolute start-2 top-2 rounded-lg bg-primary px-2.5 py-1 text-xs font-bold text-primary-foreground shadow-sm">
            مسجّل
          </span>
        )}

        {/* Purchased badge */}
        {isPurchasedNotEnrolled && (
          <span className="absolute start-2 top-2 rounded-lg bg-orange-500 px-2.5 py-1 text-xs font-bold text-white shadow-sm">
            تم الشراء
          </span>
        )}

        {/* Coverage badge (Normal sub / B2B sub / Program) */}
        {!isEnrolled && !isPurchasedNotEnrolled && canEnrollViaSub && (
          <span className="absolute start-2 top-2 inline-flex items-center gap-1 rounded-lg bg-purple-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm">
            <BadgeCheck className="size-3.5" />
            {resolvedCoverage === "b2b_sub"
              ? "مشمول باشتراك مؤسستك"
              : resolvedCoverage === "program"
                ? "مشمول ببرنامجك"
                : "مشمول باشتراكك"}
          </span>
        )}
      </Link>

      {/* Body */}
      <div className="flex flex-1 flex-col gap-2 p-4">
        <Link href={`/courses/${course.id}`}>
          <h3 className="line-clamp-2 text-sm font-bold leading-snug text-foreground transition-colors group-hover:text-primary">
            {fullname}
          </h3>
        </Link>

        {course.teacherName && <p className="text-xs text-muted-foreground">{course.teacherName}</p>}
        {shortname && shortname !== fullname && (
          <p className="text-xs text-muted-foreground">{shortname}</p>
        )}

        {categoryname && (
          <span className="inline-block w-fit rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">
            {categoryname}
          </span>
        )}

        {/* Price row with strike-through when discounted */}
        {!isFree && (
          <div className="flex items-baseline justify-end gap-2">
            {hasDiscount && (
              <span className="text-[11px] text-muted-foreground line-through">
                {course.originalPrice} {currency}
              </span>
            )}
            <span className="text-sm font-extrabold text-foreground">
              {course.price} {currency}
            </span>
          </div>
        )}

        {course.enrolledusercount != null && (
          <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
            <Users className="size-3" />
            <span>{course.enrolledusercount} طالب</span>
          </div>
        )}

        {/* CTA */}
        <div className="mt-auto pt-2">
          {isEnrolled ? (
            <Link
              href={`/courses/${course.id}`}
              className="flex items-center justify-center gap-1.5 rounded-xl bg-primary/10 py-2 text-xs font-bold text-primary transition hover:bg-primary/20"
            >
              <PlayCircle className="size-4" />
              متابعة التعلم
            </Link>
          ) : isPurchasedNotEnrolled ? (
            <button
              onClick={handleEnrollPurchased}
              disabled={enrolling}
              className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-orange-500 py-2 text-xs font-bold text-white transition hover:bg-orange-600 disabled:opacity-60"
            >
              {enrolling ? <Loader2 className="size-4 animate-spin" /> : <BookOpen className="size-4" />}
              {enrolling ? "جارٍ الانضمام..." : "انضمام للكورس"}
            </button>
          ) : isFree ? (
            <button
              onClick={handleEnrollFree}
              disabled={enrolling}
              className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-500 py-2 text-xs font-bold text-white transition hover:bg-emerald-600 disabled:opacity-60"
            >
              {enrolling ? <Loader2 className="size-4 animate-spin" /> : <BookOpen className="size-4" />}
              {enrolling ? "جارٍ الانضمام..." : "انضمام للكورس"}
            </button>
          ) : canEnrollViaSub ? (
            <button
              onClick={handleEnrollViaSubscription}
              disabled={enrolling}
              className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-purple-600 py-2 text-xs font-bold text-white transition hover:bg-purple-700 disabled:opacity-60"
            >
              {enrolling ? <Loader2 className="size-4 animate-spin" /> : <BadgeCheck className="size-4" />}
              {enrolling ? "جارٍ الانضمام..." : "انضمام للكورس"}
            </button>
          ) : (
            <button
              onClick={() => setModalOpen(true)}
              className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-blue-600 py-2 text-xs font-bold text-white transition hover:bg-blue-700"
            >
              <ShoppingCart className="size-4" />
              شراء
            </button>
          )}
          {error && <p className="mt-1.5 text-center text-[10px] text-red-600">{error}</p>}
        </div>
      </div>

      {!isFree && !isEnrolled && (
        <BuyCourseModal
          courseId={course.id}
          courseName={fullname}
          basePrice={course.price ?? 0}
          originalPrice={course.originalPrice}
          currency={currency}
          open={modalOpen}
          onClose={() => setModalOpen(false)}
        />
      )}
    </div>
  );
}
