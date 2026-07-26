import { AppShell } from "@/components/layout/app-shell";
import { callMoodleRest } from "@/lib/moodle-server";
import type { Course } from "@/features/courses/types";

interface MoodleCoursesResponse {
  courses?: Course[];
}

async function getCoursesData(): Promise<Course[]> {
  const adminToken = process.env.MOODLE_ADMIN_TOKEN;
  if (!adminToken) throw new Error("Admin token not configured");

  const result = await callMoodleRest<MoodleCoursesResponse | Course[]>({
    functionName: "core_course_get_courses_by_field",
    token: adminToken,
  });

  const rawCourses = Array.isArray(result) ? result : result?.courses ?? [];
  return rawCourses.filter((c) => c.id !== 1);
}

export default async function HomePage() {
  let courses: Course[] = [];
  let error: string | null = null;

  try {
    courses = await getCoursesData();
  } catch (e) {
    error = e instanceof Error ? e.message : "Failed to load courses";
  }

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-h1 font-bold">أكاديمية التميز</h1>
          <p className="mt-1 text-caption text-muted-foreground">
            مرحباً بك في المنصة التعليمية
          </p>
        </div>

        {error ? (
          <div className="rounded-xl border border-destructive bg-destructive/10 p-5">
            <p className="text-sm font-medium text-destructive">{error}</p>
          </div>
        ) : courses.length === 0 ? (
          <div className="rounded-xl border border-border bg-card p-8 text-center">
            <p className="text-muted-foreground">لا توجد دورات متاحة حالياً.</p>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {courses.map((course) => (
              <a
                key={course.id}
                href={`/courses/${course.id}`}
                className="rounded-xl border border-border bg-card p-5 shadow-sm transition hover:shadow-md"
              >
                <div className="text-caption font-semibold text-primary">
                  {course.fullname}
                </div>
                <p className="mt-2 text-small text-muted-foreground">
                  {course.shortname}
                </p>
              </a>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}