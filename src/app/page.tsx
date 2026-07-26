import { AppShell } from "@/components/layout/app-shell";
import { getHomeDashboardData } from "@/services/home.service";
import Image from "next/image";
import Link from "next/link";
import { User, BookOpen, Video, Layers, CreditCard } from "lucide-react";

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

        {/* Quick action banners */}
        <div className="grid gap-3 sm:grid-cols-2">
          <Link
            href="/lessons"
            className="flex items-center justify-between rounded-2xl border-2 border-primary/20 bg-primary/5 px-5 py-4 transition hover:bg-primary/10"
          >
            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10">
              <Video className="size-5 text-primary" />
            </div>
            <div className="text-end">
              <p className="text-[13px] font-bold text-foreground">حصصي</p>
              <p className="text-[11px] text-muted-foreground">احجز أو تابع دروسك</p>
            </div>
          </Link>
          <Link
            href="/courses"
            className="flex items-center justify-between rounded-2xl border-2 border-primary/20 bg-primary/5 px-5 py-4 transition hover:bg-primary/10"
          >
            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10">
              <BookOpen className="size-5 text-primary" />
            </div>
            <div className="text-end">
              <p className="text-[13px] font-bold text-foreground">الكورسات</p>
              <p className="text-[11px] text-muted-foreground">تصفح وابدأ التعلم</p>
            </div>
          </Link>
        </div>

        {/* ── الكورسات ─────────────────────────────────────────────────────── */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <Link href="/courses" className="text-sm font-semibold text-primary hover:underline">
              عرض الكل
            </Link>
            <h2 className="text-xl font-bold">الكورسات</h2>
          </div>

          {data.courses.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا توجد كورسات متاحة</p>
          ) : (
            <div className="flex gap-4 overflow-x-auto pb-2 scrollbar-none">
              {data.courses.slice(0, 10).map((course) => (
                <div
                  key={course.id}
                  className="w-[200px] shrink-0 overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                >
                  {/* Cover image */}
                  <div className="relative h-[120px] w-full bg-muted">
                    {course.courseimage ? (
                      <Image
                        src={course.courseimage}
                        alt={course.fullname}
                        fill
                        sizes="200px"
                        className="object-cover"
                        unoptimized
                      />
                    ) : (
                      <div className="flex h-full items-center justify-center">
                        <BookOpen className="size-10 text-muted-foreground/20" />
                      </div>
                    )}
                  </div>

                  <div className="p-3">
                    <h3 className="text-small font-bold text-primary line-clamp-2 text-end">
                      {course.fullname}
                    </h3>
                    <Link
                      href={`/courses/${course.id}`}
                      className="mt-3 flex items-center justify-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-[12px] font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                      <BookOpen className="size-3.5" />
                      عرض المحتوى
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* ── هيئة التدريس ─────────────────────────────────────────────────── */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <Link href="/teachers" className="text-sm font-semibold text-primary hover:underline">
              عرض الكل
            </Link>
            <h2 className="text-xl font-bold">هيئة التدريس</h2>
          </div>

          {data.teachers.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا يوجد مدرسون حالياً</p>
          ) : (
            <div className="flex gap-5 overflow-x-auto pb-2 scrollbar-none">
              {data.teachers.slice(0, 15).map((teacher) => (
                <Link
                  key={teacher.userid}
                  href="/teachers"
                  className="flex min-w-[80px] flex-col items-center gap-2"
                >
                  {/* Avatar circle */}
                  <div className="relative size-[72px] overflow-hidden rounded-full border-2 border-primary/15 bg-primary/10">
                    {teacher.photourl ? (
                      <Image
                        src={teacher.photourl.replace("/webservice/pluginfile.php", "/pluginfile.php")}
                        alt={teacher.fullname}
                        fill
                        sizes="72px"
                        className="object-cover"
                        unoptimized
                      />
                    ) : (
                      <div className="flex h-full w-full items-center justify-center">
                        <User className="size-8 text-primary/50" />
                      </div>
                    )}
                  </div>

                  {/* Name */}
                  <p className="w-[84px] text-center text-[11px] font-bold leading-tight text-foreground line-clamp-2">
                    {teacher.fullname}
                  </p>

                  {/* First subject */}
                  {teacher.subjects?.[0] && (
                    <p className="text-[10px] text-muted-foreground text-center line-clamp-1 w-[84px]">
                      {teacher.subjects[0].subject}
                    </p>
                  )}
                </Link>
              ))}
            </div>
          )}
        </section>

        {/* ── البرامج ──────────────────────────────────────────────────────── */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <Link href="/programs" className="text-sm font-semibold text-primary hover:underline">
              عرض الكل
            </Link>
            <h2 className="text-xl font-bold">البرامج</h2>
          </div>

          {data.programs.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا توجد برامج متاحة</p>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {data.programs.map((program) => (
                <div
                  key={program.id}
                  className="flex items-center justify-between rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                  <Link
                    href={`/programs`}
                    className="text-xs font-bold text-primary hover:underline"
                  >
                    فتح
                  </Link>
                  <div className="text-end">
                    {program.owned ? (
                      <span className="inline-block rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold text-emerald-600">
                        ملتحق
                      </span>
                    ) : (
                      <span className="inline-block rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-bold text-primary">
                        برنامج
                      </span>
                    )}
                    <h3 className="mt-1.5 font-bold leading-snug">{program.name}</h3>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* ── الباقات ──────────────────────────────────────────────────────── */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <Link href="/packages" className="text-sm font-semibold text-primary hover:underline">
              عرض الكل
            </Link>
            <h2 className="text-xl font-bold">الباقات</h2>
          </div>

          {data.packages.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا توجد باقات متاحة</p>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {data.packages.map((pkg) => (
                <div
                  key={pkg.id}
                  className="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className="rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary whitespace-nowrap">
                      {pkg.flexCount} حصة
                    </span>
                    <h3 className="font-bold text-end">{pkg.name}</h3>
                  </div>
                  <p className="text-xs text-muted-foreground text-end">
                    {pkg.description || "تمثل كل حصة مرنة درساً واحداً."}
                  </p>
                  <div className="flex items-center justify-between border-t pt-2">
                    <Link href="/packages" className="text-xs font-semibold text-primary hover:underline">
                      اشترك
                    </Link>
                    <span className="text-sm font-extrabold">{pkg.price} جنيه</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* ── الاشتراكات ───────────────────────────────────────────────────── */}
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <Link href="/subscriptions" className="text-sm font-semibold text-primary hover:underline">
              عرض الكل
            </Link>
            <h2 className="text-xl font-bold">الاشتراكات</h2>
          </div>

          {data.subscriptions.length === 0 ? (
            <p className="text-sm text-muted-foreground">لا توجد اشتراكات متاحة</p>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {data.subscriptions.map((sub) => (
                <div
                  key={sub.id}
                  className="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className="rounded-lg bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 whitespace-nowrap">
                      {sub.durationDays} يوم
                    </span>
                    <h3 className="font-bold text-end">{sub.name}</h3>
                  </div>
                  <div className="flex items-center justify-between border-t pt-2">
                    <Link href="/subscriptions" className="text-xs font-semibold text-primary hover:underline">
                      اشترك
                    </Link>
                    <span className="text-sm font-extrabold">{sub.price} جنيه</span>
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
