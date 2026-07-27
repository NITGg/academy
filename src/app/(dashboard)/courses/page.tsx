import type { Metadata } from "next";
import Link from "next/link";
import { BookOpen } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { getCoursesPageData, getMyCourses } from "@/features/courses/server";
import { getMySubscriptions } from "@/features/subscriptions/server";
import { CategoryFilter } from "@/features/courses/components/CategoryFilter";
import { PaginatedCourses, PaginatedMyCourses } from "@/features/courses/components/PaginatedCourseList";
import { cn } from "@/lib/utils";

export const metadata: Metadata = { title: "الكورسات" };

interface CoursesPageProps {
  searchParams: Promise<{ tab?: string; categoryId?: string; search?: string }>;
}

export default async function CoursesPage({ searchParams }: CoursesPageProps) {
  const { tab, categoryId, search } = await searchParams;

  const isMy = tab === "my";

  const session = await getSessionFromCookie();

  // Fetch in parallel — only load what's needed for the active tab
  const [{ categories, courses }, myCourses, mySubscriptions] = await Promise.all([
    isMy
      ? Promise.resolve({ categories: [], courses: [] })
      : getCoursesPageData({ categoryId, search, wstoken: session?.wstoken }),
    isMy && session
      ? getMyCourses(session.wstoken, session.user.id)
      : Promise.resolve([]),
    !isMy && session
      ? getMySubscriptions(session.wstoken)
      : Promise.resolve([]),
  ]);

  // Courses unlocked by any active subscription (normal or B2B) → free on-demand enrol.
  const coveredCourseIds = mySubscriptions
    .filter((s) => s.status === "active")
    .flatMap((s) => (s.courses ?? []).map((c) => c.id));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <BookOpen className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الكورسات</h1>
      </div>

      {/* Tab bar */}
      <div className="flex border-b border-border">
        <Link
          href="/courses?tab=my"
          className={cn(
            "flex-1 py-2.5 text-center text-small font-medium transition-colors",
            isMy
              ? "border-b-2 border-primary text-primary"
              : "text-muted-foreground hover:text-foreground"
          )}
        >
          كورساتي
        </Link>
        <Link
          href="/courses"
          className={cn(
            "flex-1 py-2.5 text-center text-small font-medium transition-colors",
            !isMy
              ? "border-b-2 border-primary text-primary"
              : "text-muted-foreground hover:text-foreground"
          )}
        >
          الكورسات
        </Link>
      </div>

      {/* ── كورساتي tab ──────────────────────────────────────────────────── */}
      {isMy && (
        <>
          {!session ? (
            <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-border py-20 text-center">
              <BookOpen className="size-10 text-muted-foreground/40" />
              <p className="text-caption text-muted-foreground">
                يجب تسجيل الدخول لعرض كورساتك
              </p>
              <Link
                href="/login"
                className="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground"
              >
                تسجيل الدخول
              </Link>
            </div>
          ) : myCourses.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-border py-20 text-center">
              <BookOpen className="size-10 text-muted-foreground/40" />
              <p className="text-caption text-muted-foreground">
                لم تسجّل في أي كورس بعد
              </p>
              <Link
                href="/courses"
                className="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground"
              >
                تصفح الكورسات
              </Link>
            </div>
          ) : (
            <PaginatedMyCourses courses={myCourses} pageSize={12} />
          )}
        </>
      )}

      {/* ── الكورسات tab ─────────────────────────────────────────────────── */}
      {!isMy && (
        <>
          {categories.length > 0 && (
            <CategoryFilter
              categories={categories}
              activeCategoryId={categoryId}
              searchQuery={search}
            />
          )}

          {courses.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-border py-20 text-center">
              <BookOpen className="size-10 text-muted-foreground/40" />
              <p className="text-caption text-muted-foreground">
                لا توجد كورسات في هذا القسم
              </p>
            </div>
          ) : (
            <PaginatedCourses courses={courses} pageSize={12} coveredCourseIds={coveredCourseIds} />
          )}
        </>
      )}
    </div>
  );
}
