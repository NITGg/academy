import Image from "next/image";
import Link from "next/link";
import { Users, ArrowRight, ArrowLeft, Tag, BadgeCheck } from "lucide-react";
import { getLocale } from "next-intl/server";
import type { Course } from "../types";
import type { CourseAccess } from "../server-detail";
import { EnrollButton } from "./EnrollButton";
import { CourseBuyButton } from "./CourseBuyButton";
import { SubscriptionEnrollButton } from "./SubscriptionEnrollButton";

interface CourseHeroProps {
  course: Course;
  access: CourseAccess;
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, "").replace(/&[a-z]+;/gi, " ").trim();
}

export async function CourseHero({ course, access }: CourseHeroProps) {
  const locale = await getLocale();
  const isRtl = locale === "ar";
  const BackArrow = isRtl ? ArrowRight : ArrowLeft;

  // Read the SAME enriched course fields the catalog card uses, so the two agree.
  const isFree = access.isFree;
  const isEnrolled = access.isEnrolled;
  const isPurchased = access.isPurchased;
  const hasPendingPayment = access.hasPendingPayment;

  const displayPrice = course.price;
  const originalPrice = course.originalPrice;
  const hasDiscount = !isFree && originalPrice != null && originalPrice > (displayPrice ?? 0);
  const currency = course.currency ?? (isRtl ? "جنيه" : "EGP");

  const priceLabel = isFree
    ? locale === "ar" ? "مجاني" : "Free"
    : `${displayPrice} ${currency}`;

  const summary = course.summary ? stripHtml(course.summary) : "";

  return (
    <div className="space-y-6">
      {/* Back link */}
      <Link
        href="/courses"
        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
      >
        <BackArrow className="size-4" />
        {locale === "ar" ? "الكورسات" : "Courses"}
      </Link>

      {/* Hero card */}
      <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        {/* Course image */}
        {course.courseimage && (
          <div className="relative aspect-[21/6] w-full overflow-hidden bg-muted">
            <Image
              src={course.courseimage}
              alt={course.fullname}
              fill
              sizes="(max-width: 1280px) 100vw, 1280px"
              className="object-cover"
              priority
            />
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
          </div>
        )}

        <div className="p-6 space-y-4">
          {/* Badges row */}
          <div className="flex flex-wrap gap-2">
            {course.categoryname && (
              <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                <Tag className="size-3" />
                {course.categoryname}
              </span>
            )}
            <span
              className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ${
                isFree
                  ? "bg-emerald-500/10 text-emerald-600"
                  : "bg-orange-500/10 text-orange-600"
              }`}
            >
              {priceLabel}
            </span>
            {(isEnrolled || isPurchased) && (
              <span className="inline-flex items-center rounded-full bg-primary px-3 py-1 text-xs font-bold text-primary-foreground">
                {locale === "ar" ? "مسجّل" : "Enrolled"}
              </span>
            )}
            {!isEnrolled && !isPurchased && access.coveredBySubscription && (
              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-600">
                <BadgeCheck className="size-3.5" />
                {locale === "ar" ? "مشمول باشتراكك" : "Included in your subscription"}
              </span>
            )}
          </div>

          {/* Title */}
          <h1 className="text-h1 font-bold leading-snug">{course.fullname}</h1>

          {/* Teacher */}
          {course.teacherName && (
            <p className="text-caption text-muted-foreground">
              {locale === "ar" ? "المدرس:" : "Instructor:"}{" "}
              <span className="font-medium text-foreground">{course.teacherName}</span>
            </p>
          )}

          {/* Stats row */}
          {course.enrolledusercount != null && (
            <div className="flex items-center gap-1.5 text-small text-muted-foreground">
              <Users className="size-4" />
              <span>
                {course.enrolledusercount}{" "}
                {locale === "ar" ? "طالب مسجّل" : "enrolled students"}
              </span>
            </div>
          )}

          {/* Description */}
          {summary && (
            <p className="text-caption text-muted-foreground leading-relaxed line-clamp-4">
              {summary}
            </p>
          )}

          {/* CTA */}
          {!isEnrolled && !isPurchased && (
            <div className="pt-2">
              {isFree ? (
                <EnrollButton
                  courseId={course.id}
                  isFree
                  price={displayPrice}
                  currency={currency}
                  hasPendingPayment={hasPendingPayment}
                  locale={locale}
                />
              ) : access.coveredBySubscription ? (
                <SubscriptionEnrollButton courseId={course.id} locale={locale} />
              ) : (
                <CourseBuyButton
                  courseId={course.id}
                  courseName={course.fullname}
                  price={displayPrice ?? 0}
                  originalPrice={hasDiscount ? originalPrice : undefined}
                  currency={currency}
                  label={
                    locale === "ar"
                      ? `اشترِ مقابل ${displayPrice} ${currency}`
                      : `Buy for ${displayPrice} ${currency}`
                  }
                />
              )}
            </div>
          )}

          {/* {(isEnrolled || isPurchased) && (
            <div className="pt-2">
              <a
                href="#course-content"
                className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
              >
                <BookOpen className="size-4" />
                {locale === "ar" ? "ابدأ التعلم" : "Start Learning"}
              </a>
            </div>
          )} */}
        </div>
      </div>
    </div>
  );
}
