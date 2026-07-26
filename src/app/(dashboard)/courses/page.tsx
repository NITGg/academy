import type { Metadata } from "next";
import { BookOpen } from "lucide-react";
import { getCoursesPageData } from "@/features/courses/server";
import { CourseCard } from "@/features/courses/components/CourseCard";
import { CategoryFilter } from "@/features/courses/components/CategoryFilter";

export const metadata: Metadata = { title: "الكورسات" };


interface CoursesPageProps {
  searchParams: Promise<{ categoryId?: string; search?: string }>;
}

export default async function CoursesPage({ searchParams }: CoursesPageProps) {
  const { categoryId, search } = await searchParams;

  const { categories, courses } = await getCoursesPageData({ categoryId, search });

  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex items-center gap-3">
        <BookOpen className="size-6 text-primary" />
        <div>
          <h1 className="text-h1 font-bold">الكورسات</h1>
          <p className="text-small text-muted-foreground">
            {courses.length > 0 ? `${courses.length} كورس متاح` : "جاري تحميل الكورسات..."}
          </p>
        </div>
      </div>

      {/* Category filter tabs */}
      {categories.length > 0 && (
        <CategoryFilter
          categories={categories}
          activeCategoryId={categoryId}
          searchQuery={search}
        />
      )}

      {/* Course grid */}
      {courses.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-border py-20 text-center">
          <BookOpen className="size-10 text-muted-foreground/40" />
          <p className="text-caption text-muted-foreground">لا توجد كورسات في هذا القسم</p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {courses.map((course) => (
            <CourseCard key={course.id} course={course} />
          ))}
        </div>
      )}
    </div>
  );
}
