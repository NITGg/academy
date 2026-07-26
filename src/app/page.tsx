import { AppShell } from "@/components/layout/app-shell";
import { getHomeDashboardData } from "@/services/home.service";
import Link from "next/link";
import { User, BookOpen, Layers, CreditCard, Award } from "lucide-react";

export default async function HomePage() {
  const data = await getHomeDashboardData();

  return (
    <AppShell>
      <div className="space-y-10 pb-10">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold">أكاديمية التميز</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            مرحباً بك في المنصة التعليمية
          </p>
        </div>

        {/* 1. الكورسات (Courses) */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-bold">الكورسات</h2>
            <Link
              href="/courses"
              className="text-sm font-semibold text-primary hover:underline"
            >
              عرض الكل
            </Link>
          </div>
          {data.courses.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا توجد كورسات متاحة</p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {data.courses.slice(0, 6).map((course) => (
                <div
                  key={course.id}
                  className="flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-sm transition hover:shadow-md"
                >
                  <div className="space-y-2">
                    <h3 className="font-bold text-primary">{course.fullname}</h3>
                    <p className="text-xs text-muted-foreground line-clamp-2">
                      {course.shortname}
                    </p>
                  </div>
                  <Link
                    href={`/courses/${course.id}`}
                    className="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground transition hover:opacity-90"
                  >
                    <BookOpen className="h-4 w-4" />
                    عرض المحتوى
                  </Link>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* 2. هيئة التدريس (Teachers) */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-bold">هيئة التدريس</h2>
            <Link
              href="/teachers"
              className="text-sm font-semibold text-primary hover:underline"
            >
              عرض الكل
            </Link>
          </div>
          {data.teachers.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا يوجد مدرسون حالياً</p>
          ) : (
            <div className="flex gap-4 overflow-x-auto pb-2 scrollbar-none">
              {data.teachers.map((teacher) => (
                <div
                  key={teacher.userId}
                  className="flex min-w-[120px] flex-col items-center space-y-2 rounded-2xl border border-border bg-card p-4 text-center shadow-sm"
                >
                  {teacher.photoUrl ? (
                    <img
                      src={teacher.photoUrl}
                      alt={teacher.fullName}
                      className="h-16 w-16 rounded-full object-cover"
                    />
                  ) : (
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                      <User className="h-8 w-8" />
                    </div>
                  )}
                  <p className="text-xs font-bold line-clamp-1">
                    {teacher.fullName}
                  </p>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* 3. البرامج (Programs) */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-bold">البرامج</h2>
            <Link
              href="/programs"
              className="text-sm font-semibold text-primary hover:underline"
            >
              عرض الكل
            </Link>
          </div>
          {data.programs.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا توجد برامج متاحة</p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {data.programs.map((program) => (
                <div
                  key={program.id}
                  className="flex items-center justify-between rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                  <div>
                    <span className="inline-block rounded-md bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600">
                      {program.owned ? "ملتحق" : "برنامج"}
                    </span>
                    <h3 className="mt-2 font-bold">{program.name}</h3>
                  </div>
                  <Link
                    href={`/programs/${program.id}`}
                    className="text-xs font-bold text-primary hover:underline"
                  >
                    فتح
                  </Link>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* 4. الباقات (Packages) */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-bold">الباقات</h2>
            <Link
              href="/packages"
              className="text-sm font-semibold text-primary hover:underline"
            >
              عرض الكل
            </Link>
          </div>
          {data.packages.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا توجد باقات متاحة</p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {data.packages.map((pkg) => (
                <div
                  key={pkg.id}
                  className="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                  <div className="flex items-center justify-between">
                    <h3 className="font-bold text-primary">{pkg.name}</h3>
                    <span className="rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">
                      {pkg.flexCount} حصة
                    </span>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    {pkg.description || "تمثل كل حصة درساً مرناً واحداً."}
                  </p>
                  <div className="flex items-center justify-between pt-2 border-t">
                    <span className="text-sm font-extrabold">
                      {pkg.price} جنيه
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* 5. الاشتراكات (Subscriptions) */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-bold">الاشتراكات</h2>
            <Link
              href="/subscriptions"
              className="text-sm font-semibold text-primary hover:underline"
            >
              عرض الكل
            </Link>
          </div>
          {data.subscriptions.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              لا توجد اشتراكات متاحة
            </p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {data.subscriptions.map((sub) => (
                <div
                  key={sub.id}
                  className="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                  <div className="flex items-center justify-between">
                    <h3 className="font-bold">{sub.name}</h3>
                    <span className="rounded-lg bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold text-blue-600">
                      {sub.durationDays} يوم
                    </span>
                  </div>
                  <div className="flex items-center justify-between pt-2 border-t">
                    <span className="text-sm font-extrabold">
                      {sub.price} جنيه
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>
    </AppShell>
  );
}