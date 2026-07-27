"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { cn } from "@/lib/utils";
import {
  User,
  BookOpen,
  Video,
  Layers,
  CreditCard,
  Building2,
  CheckCircle2,
  X,
  Loader2,
  Tag,
  ShieldCheck,
  Check,
  Calendar,
  Package,
  Settings2,
} from "lucide-react";

function formatDate(ts: number): string {
  if (!ts) return "غير محدد";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}
import type { Course } from "@/features/courses/types";
import type { Teacher } from "@/features/teachers/types";
import type { CatalogueProgram, MyProgram } from "@/features/programs/types";
import type { AvailablePackage, MyPackage } from "@/features/packages/types";
import type { AvailableSubscription, MySubscription } from "@/features/subscriptions/types";
import type { EnrolledCourse } from "@/features/courses/server";
import { startPackageCheckout } from "@/features/packages/actions";
import { startSubscriptionCheckout } from "@/features/subscriptions/actions";
import { startProgramCheckout, joinFreeProgram } from "@/features/programs/actions";
import { CourseCard } from "@/features/courses/components/CourseCard";
import { MyCourseCard } from "@/features/courses/components/MyCourseCard";

interface HomePageClientProps {
  courses: Course[];
  myCourses: EnrolledCourse[];
  teachers: Teacher[];
  programs: CatalogueProgram[];
  packages: AvailablePackage[];
  subscriptions: AvailableSubscription[];
  myPackages: MyPackage[];
  mySubscriptions: MySubscription[];
  myPrograms: MyProgram[];
  isLoggedIn: boolean;
}

export function HomePageClient({
  courses,
  myCourses = [],
  teachers,
  programs,
  packages,
  subscriptions,
  myPackages = [],
  mySubscriptions = [],
  myPrograms = [],
  isLoggedIn,
}: HomePageClientProps) {
  // Active states check
  const activePackage = myPackages.find(
    (p) => p.status === "active" || (p.remaining_flex ?? 0) > 0,
  );
  const activeSubscription = mySubscriptions.find((s) => s.status === "active");
  // Courses unlocked by any of the user's active subscriptions (normal or B2B) → free on-demand enrol.
  const coveredCourseIds = new Set<number>(
    mySubscriptions
      .filter((s) => s.status === "active")
      .flatMap((s) => (s.courses ?? []).map((c) => c.id)),
  );
  // Subscription plans the user already administers as a B2B admin (active B2B purchase).
  const ownedB2bSubIds = new Set(
    mySubscriptions
      .filter((s) => s.type === "b2b" && s.status === "active")
      .map((s) => s.subscriptionid),
  );
  const ownedProgramIds = new Set(
    myPrograms.map((mp) => mp.id).concat(programs.filter((p) => p.owned === 1).map((p) => p.id)),
  );

  // Modals state
  const [selectedProgram, setSelectedProgram] = useState<CatalogueProgram | null>(null);
  const [selectedPackage, setSelectedPackage] = useState<AvailablePackage | null>(null);
  const [selectedSub, setSelectedSub] = useState<AvailableSubscription | null>(null);
  const [b2bSub, setB2bSub] = useState<AvailableSubscription | null>(null);
  const [selectedSeats, setSelectedSeats] = useState<number>(10);

  // Loading & Error states
  const [loadingId, setLoadingId] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  // Handlers
  async function handlePackageBuy(pkgId: number) {
    if (!isLoggedIn) {
      window.location.href = "/login";
      return;
    }
    setLoadingId(`pkg-${pkgId}`);
    setActionError(null);
    const res = await startPackageCheckout(pkgId);
    setLoadingId(null);

    if (res.needsAuth) {
      window.location.href = "/login";
    } else if (res.checkoutUrl) {
      window.location.href = res.checkoutUrl;
    } else {
      setActionError(res.error || "تعذّر بدء عملية الدفع للباقة");
    }
  }

  async function handleSubscriptionBuy(subId: number, type: "normal" | "b2b" = "normal", seats?: number) {
    if (!isLoggedIn) {
      window.location.href = "/login";
      return;
    }
    setLoadingId(`sub-${subId}-${type}`);
    setActionError(null);
    const res = await startSubscriptionCheckout({
      subscriptionId: subId,
      type,
      seats,
    });
    setLoadingId(null);

    if (res.needsAuth) {
      window.location.href = "/login";
    } else if (res.checkoutUrl) {
      window.location.href = res.checkoutUrl;
    } else {
      setActionError(res.error || "تعذّر بدء عملية الدفع للاشتراك");
    }
  }

  async function handleProgramBuyOrJoin(prog: CatalogueProgram) {
    if (!isLoggedIn) {
      window.location.href = "/login";
      return;
    }
    setLoadingId(`prog-${prog.id}`);
    setActionError(null);

    if (prog.free === 1 && prog.joinable === 1) {
      const res = await joinFreeProgram(prog.id);
      setLoadingId(null);
      if (res.success) {
        window.location.reload();
      } else {
        setActionError(res.error || "تعذّر الانضمام للبرنامج");
      }
    } else {
      const res = await startProgramCheckout(prog.id);
      setLoadingId(null);
      if (res.needsAuth) {
        window.location.href = "/login";
      } else if (res.checkoutUrl) {
        window.location.href = res.checkoutUrl;
      } else {
        setActionError(res.error || "تعذّر بدء عملية الدفع للبرنامج");
      }
    }
  }

  return (
    <div className="space-y-10 pb-10">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-foreground">أكاديمية التميز</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          مرحباً بك في المنصة التعليمية
        </p>
      </div>

      {/* Action Errors Banner */}
      {actionError && (
        <div className="flex items-center justify-between rounded-2xl bg-destructive/10 p-4 text-xs font-semibold text-destructive">
          <span>{actionError}</span>
          <button onClick={() => setActionError(null)}>
            <X className="size-4" />
          </button>
        </div>
      )}

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

      {/* ── كورساتي (My Courses) ─────────────────────────────────────────── */}
      {myCourses.length > 0 && (
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <Link href="/courses?tab=my" className="text-sm font-semibold text-primary hover:underline">
              عرض الكل
            </Link>
            <h2 className="text-xl font-bold text-foreground">كورساتي</h2>
          </div>

          <div className="flex gap-4 overflow-x-auto pb-2 scrollbar-none">
            {myCourses.slice(0, 10).map((course) => (
              <div key={course.id} className="w-[240px] shrink-0">
                <MyCourseCard course={course} />
              </div>
            ))}
          </div>
        </section>
      )}

      {/* ── الكورسات ─────────────────────────────────────────────────────── */}
      <section className="space-y-4">
        <div className="flex items-center justify-between">
          <Link href="/courses" className="text-sm font-semibold text-primary hover:underline">
            عرض الكل
          </Link>
          <h2 className="text-xl font-bold text-foreground">الكورسات</h2>
        </div>

        {courses.length === 0 ? (
          <p className="text-sm text-muted-foreground">لا توجد كورسات متاحة</p>
        ) : (
          <div className="flex gap-4 overflow-x-auto pb-2 scrollbar-none">
            {courses.slice(0, 10).map((course) => (
              <div key={course.id} className="w-[240px] shrink-0">
                <CourseCard course={course} coveredBySubscription={coveredCourseIds.has(course.id)} />
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
          <h2 className="text-xl font-bold text-foreground">هيئة التدريس</h2>
        </div>

        {teachers.length === 0 ? (
          <p className="text-sm text-muted-foreground">لا يوجد مدرسون حالياً</p>
        ) : (
          <div className="flex gap-5 overflow-x-auto pb-2 scrollbar-none">
            {teachers.slice(0, 15).map((teacher) => (
              <Link
                key={teacher.userid}
                href={`/teachers/${teacher.userid}`}
                className="flex min-w-[80px] flex-col items-center gap-2"
              >
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
                <p className="w-[84px] text-center text-[11px] font-bold leading-tight text-foreground line-clamp-2">
                  {teacher.fullname}
                </p>
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

      {/* ── البرامج (Programs) ────────────────────────────────────────────── */}
      <section className="space-y-4">
        <div className="flex items-center justify-between">
          <Link href="/programs" className="text-sm font-semibold text-primary hover:underline">
            عرض الكل
          </Link>
          <h2 className="text-xl font-bold text-foreground">البرامج</h2>
        </div>

        {programs.length === 0 ? (
          <p className="text-sm text-muted-foreground">لا توجد برامج متاحة</p>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {programs.map((program) => {
              const isOwned = ownedProgramIds.has(program.id);
              return (
                <div
                  key={program.id}
                  onClick={() => {
                    window.location.href = `/programs/${program.id}`;
                  }}
                  className="cursor-pointer flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-sm transition hover:border-primary/50 hover:shadow-md"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                      {isOwned ? (
                        <span className="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold text-emerald-600">
                          ملتحق
                        </span>
                      ) : program.free === 1 ? (
                        <span className="rounded-full bg-blue-500/10 px-2.5 py-0.5 text-[10px] font-bold text-blue-600">
                          مجاني
                        </span>
                      ) : (
                        <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-bold text-primary">
                          برنامج مدفوع
                        </span>
                      )}
                      {program.offer && (
                        <span className="rounded-full bg-destructive/10 px-2 py-0.5 text-[10px] font-bold text-destructive">
                          {program.offer.label}
                        </span>
                      )}
                    </div>
                    <h3 className="font-bold text-end text-foreground text-small line-clamp-1">
                      {program.name}
                    </h3>
                  </div>

                  {program.description && (
                    <p className="mt-2 text-xs text-muted-foreground text-end line-clamp-2">
                      {program.description}
                    </p>
                  )}

                  <div className="mt-4 space-y-3 border-t border-border pt-3">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-muted-foreground">السعر:</span>
                      <div>
                        {program.free === 1 ? (
                          <span className="font-bold text-emerald-600">مجاناً</span>
                        ) : program.offer ? (
                          <div className="flex items-center gap-1.5">
                            <span className="text-[11px] text-muted-foreground line-through">
                              {program.offer.original} {program.currency || "EGP"}
                            </span>
                            <span className="text-xs font-extrabold text-foreground">
                              {program.offer.final} {program.currency || "EGP"}
                            </span>
                          </div>
                        ) : (
                          <span className="text-xs font-extrabold text-foreground">
                            {program.price} {program.currency || "EGP"}
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <Link
                        href={`/programs/${program.id}`}
                        onClick={(e) => e.stopPropagation()}
                        className="flex-1 text-center rounded-xl bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground transition hover:opacity-90"
                      >
                        تصفح
                      </Link>

                      {!isOwned && (
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            handleProgramBuyOrJoin(program);
                          }}
                          disabled={loadingId === `prog-${program.id}`}
                          className="flex-1 flex items-center justify-center gap-1.5 rounded-xl border border-primary/30 bg-primary/5 px-3.5 py-1.5 text-xs font-bold text-primary transition hover:bg-primary/10 disabled:opacity-50"
                        >
                          {loadingId === `prog-${program.id}` ? (
                            <Loader2 className="size-3.5 animate-spin" />
                          ) : program.free === 1 ? (
                            "انضمام"
                          ) : (
                            "اشترك الآن"
                          )}
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* ── الباقات (Packages) ────────────────────────────────────────────── */}
      <section className="space-y-4">
        <div className="flex items-center justify-between">
          <Link href="/packages" className="text-sm font-semibold text-primary hover:underline">
            عرض الكل
          </Link>
          <h2 className="text-xl font-bold text-foreground">الباقات</h2>
        </div>

        {packages.length === 0 ? (
          <p className="text-sm text-muted-foreground">لا توجد باقات متاحة</p>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {packages.map((pkg) => {
              const isUserPackageActive = Boolean(activePackage);
              const isThisPackageActive =
                Boolean(activePackage) &&
                (Number(activePackage?.packageid) === Number(pkg.id) ||
                  Number(activePackage?.id) === Number(pkg.id) ||
                  activePackage?.name === pkg.name);

              return (
                <div
                  key={pkg.id}
                  onClick={() => setSelectedPackage(pkg)}
                  className="cursor-pointer space-y-3 rounded-2xl border border-border bg-card p-5 shadow-sm transition hover:border-primary/50 hover:shadow-md"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <span className="rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary whitespace-nowrap">
                        {pkg.flex_count} حصة
                      </span>
                      {isThisPackageActive ? (
                        <span className="rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 whitespace-nowrap">
                          باقة نشطة
                        </span>
                      ) : pkg.offer ? (
                        <span className="rounded-lg bg-destructive/10 px-2 py-1 text-xs font-bold text-destructive whitespace-nowrap">
                          {pkg.offer.label}
                        </span>
                      ) : null}
                    </div>
                    <h3 className="font-bold text-end text-foreground text-small">{pkg.name}</h3>
                  </div>

                  <p className="text-xs text-muted-foreground text-end line-clamp-2">
                    {pkg.description || "تمثل كل حصة مرنة درساً واحداً مع أي مدرس."}
                  </p>

                  <div className="flex items-center justify-between border-t border-border pt-2">
                    <button
                      type="button"
                      disabled={isUserPackageActive || loadingId === `pkg-${pkg.id}`}
                      onClick={(e) => {
                        e.stopPropagation();
                        handlePackageBuy(pkg.id);
                      }}
                      className="rounded-xl bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                    >
                      {loadingId === `pkg-${pkg.id}` ? (
                        <Loader2 className="size-3.5 animate-spin" />
                      ) : isThisPackageActive ? (
                        "باقة مفعلة"
                      ) : isUserPackageActive ? (
                        "لديك باقة مفعلة"
                      ) : (
                        "اشترك"
                      )}
                    </button>

                    <div>
                      {pkg.offer ? (
                        <div className="flex items-center gap-1.5">
                          <span className="text-[11px] text-muted-foreground line-through">
                            {pkg.offer.original} جنيه
                          </span>
                          <span className="text-sm font-extrabold text-foreground">
                            {pkg.offer.final} جنيه
                          </span>
                        </div>
                      ) : (
                        <span className="text-sm font-extrabold text-foreground">{pkg.price} جنيه</span>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* ── الاشتراكات (Subscriptions) ────────────────────────────────────── */}
      <section className="space-y-4">
        <div className="flex items-center justify-between">
          <Link href="/subscriptions" className="text-sm font-semibold text-primary hover:underline">
            عرض الكل
          </Link>
          <h2 className="text-xl font-bold text-foreground">الاشتراكات</h2>
        </div>

        {subscriptions.length === 0 ? (
          <p className="text-sm text-muted-foreground">لا توجد اشتراكات متاحة</p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {subscriptions.map((sub) => {
              const isUserSubActive = Boolean(activeSubscription);
              const isThisSubActive =
                activeSubscription &&
                (activeSubscription.subscriptionid === sub.id || activeSubscription.id === sub.id);
              const ownsB2b = ownedB2bSubIds.has(sub.id);
              const b2bEnabled = Number(sub.b2b_enabled) === 1;
              const showB2bButton = ownsB2b || b2bEnabled; // not every plan offers B2B

              return (
                <div
                  key={sub.id}
                  onClick={() => setSelectedSub(sub)}
                  className="cursor-pointer space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm transition hover:border-primary/50 hover:shadow-md"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <span className="rounded-lg bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 whitespace-nowrap">
                        {sub.duration_days} يوم
                      </span>
                      {isThisSubActive ? (
                        <span className="rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 whitespace-nowrap">
                          اشتراك نشط
                        </span>
                      ) : sub.offer ? (
                        <span className="rounded-lg bg-destructive/10 px-2 py-1 text-xs font-bold text-destructive whitespace-nowrap">
                          {sub.offer.label}
                        </span>
                      ) : null}
                    </div>
                    <h3 className="font-bold text-end text-foreground text-small">{sub.name}</h3>
                  </div>

                  {sub.description && (
                    <p className="text-xs text-muted-foreground text-end line-clamp-2">
                      {sub.description}
                    </p>
                  )}

                  <div className="flex items-center justify-between border-t border-border pt-2">
                    <span className="text-xs text-muted-foreground">السعر الفردي:</span>
                    <div>
                      {sub.offer ? (
                        <div className="flex items-center gap-1.5">
                          <span className="text-[11px] text-muted-foreground line-through">
                            {sub.offer.original} جنيه
                          </span>
                          <span className="text-sm font-extrabold text-foreground">
                            {sub.offer.final} جنيه
                          </span>
                        </div>
                      ) : (
                        <span className="text-sm font-extrabold text-foreground">{sub.price} جنيه</span>
                      )}
                    </div>
                  </div>

                  {/* ── 2 Purchase Buttons as requested ── */}
                  <div className="flex items-center gap-2 pt-1" onClick={(e) => e.stopPropagation()}>
                    {/* Normal Subscription Button */}
                    <button
                      type="button"
                      disabled={isUserSubActive || loadingId === `sub-${sub.id}-normal`}
                      onClick={() => handleSubscriptionBuy(sub.id, "normal")}
                      className={cn(
                        "flex items-center justify-center gap-1 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50",
                        showB2bButton ? "flex-1" : "w-full",
                      )}
                    >
                      {loadingId === `sub-${sub.id}-normal` ? (
                        <Loader2 className="size-3.5 animate-spin" />
                      ) : isThisSubActive ? (
                        "مشترك"
                      ) : isUserSubActive ? (
                        "لديك اشتراك"
                      ) : (
                        "اشتراك فردي"
                      )}
                    </button>

                    {/* B2B button — only for plans that allow B2B; "manage" when already owned */}
                    {ownsB2b ? (
                      <Link
                        href="/subscriptions?tab=b2b"
                        onClick={(e) => e.stopPropagation()}
                        className="flex-1 flex items-center justify-center gap-1 rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-3 py-2 text-xs font-bold text-emerald-600 transition hover:bg-emerald-500/10"
                      >
                        <Settings2 className="size-3.5" />
                        إدارة اشتراك B2B
                      </Link>
                    ) : b2bEnabled ? (
                      <button
                        type="button"
                        onClick={() => {
                          setB2bSub(sub);
                          if (sub.seat_options && sub.seat_options.length > 0) {
                            setSelectedSeats(sub.seat_options[0].seats);
                          }
                        }}
                        className="flex-1 flex items-center justify-center gap-1 rounded-xl border border-primary/30 bg-primary/5 px-3 py-2 text-xs font-bold text-primary transition hover:bg-primary/10"
                      >
                        <Building2 className="size-3.5" />
                        اشتراك B2B
                      </button>
                    ) : null}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* ── Program Details Modal ── */}
      {selectedProgram && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-lg rounded-2xl bg-card p-6 shadow-xl border border-border space-y-4 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <h3 className="text-base font-bold text-foreground">{selectedProgram.name}</h3>
              <button onClick={() => setSelectedProgram(null)} className="text-muted-foreground hover:text-foreground">
                <X className="size-5" />
              </button>
            </div>

            {selectedProgram.description && (
              <div className="space-y-1">
                <h4 className="text-xs font-bold text-foreground">وصف البرنامج:</h4>
                <p className="text-xs text-muted-foreground leading-relaxed">{selectedProgram.description}</p>
              </div>
            )}

            <div className="rounded-xl bg-muted/40 p-4 space-y-2">
              <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">حالة الاشتراك:</span>
                <span className="font-bold">
                  {ownedProgramIds.has(selectedProgram.id)
                    ? "ملتحق بالفعل"
                    : selectedProgram.free === 1
                    ? "برنامج مجاني"
                    : "برنامج مدفوع"}
                </span>
              </div>

              <div className="flex items-center justify-between text-xs border-t border-border pt-2">
                <span className="text-muted-foreground">السعر:</span>
                <div>
                  {selectedProgram.free === 1 ? (
                    <span className="font-bold text-emerald-600">مجاناً</span>
                  ) : selectedProgram.offer ? (
                    <div className="flex items-center gap-1.5">
                      <span className="text-muted-foreground line-through">
                        {selectedProgram.offer.original} {selectedProgram.currency || "EGP"}
                      </span>
                      <span className="font-bold text-foreground">
                        {selectedProgram.offer.final} {selectedProgram.currency || "EGP"}
                      </span>
                    </div>
                  ) : (
                    <span className="font-bold text-foreground">
                      {selectedProgram.price} {selectedProgram.currency || "EGP"}
                    </span>
                  )}
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setSelectedProgram(null)}
                className="rounded-xl px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted"
              >
                إغلاق
              </button>
              {ownedProgramIds.has(selectedProgram.id) ? (
                <Link
                  href="/programs"
                  className="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white transition hover:bg-emerald-700"
                >
                  فتح البرامج
                </Link>
              ) : (
                <button
                  type="button"
                  onClick={() => handleProgramBuyOrJoin(selectedProgram)}
                  disabled={loadingId === `prog-${selectedProgram.id}`}
                  className="flex items-center gap-2 rounded-xl bg-primary px-5 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                >
                  {loadingId === `prog-${selectedProgram.id}` ? (
                    <Loader2 className="size-4 animate-spin" />
                  ) : selectedProgram.free === 1 ? (
                    "انضمام مجاناً"
                  ) : (
                    "شراء وإتمام الدفع"
                  )}
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ── Package Details Modal ── */}
      {selectedPackage && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl border border-border space-y-4">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <h3 className="text-base font-bold text-foreground">{selectedPackage.name}</h3>
              <button onClick={() => setSelectedPackage(null)} className="text-muted-foreground hover:text-foreground">
                <X className="size-5" />
              </button>
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between rounded-xl bg-primary/5 p-3">
                <span className="text-xs font-bold text-primary">عدد الحصص المرنة:</span>
                <span className="text-sm font-extrabold text-primary">{selectedPackage.flex_count} حصة</span>
              </div>

              {selectedPackage.description && (
                <p className="text-xs text-muted-foreground leading-relaxed">{selectedPackage.description}</p>
              )}

              <div className="flex items-center justify-between rounded-xl bg-muted/40 p-3 text-xs">
                <span className="text-muted-foreground">السعر:</span>
                <div>
                  {selectedPackage.offer ? (
                    <div className="flex items-center gap-1.5">
                      <span className="text-muted-foreground line-through">
                        {selectedPackage.offer.original} جنيه
                      </span>
                      <span className="font-bold text-foreground">{selectedPackage.offer.final} جنيه</span>
                    </div>
                  ) : (
                    <span className="font-bold text-foreground">{selectedPackage.price} جنيه</span>
                  )}
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setSelectedPackage(null)}
                className="rounded-xl px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted"
              >
                إغلاق
              </button>
              <button
                type="button"
                disabled={Boolean(activePackage) || loadingId === `pkg-${selectedPackage.id}`}
                onClick={() => handlePackageBuy(selectedPackage.id)}
                className="flex items-center gap-2 rounded-xl bg-primary px-5 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
              >
                {loadingId === `pkg-${selectedPackage.id}` ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : activePackage ? (
                  "لديك باقة مفعلة"
                ) : (
                  "اشترك في الباقة"
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Subscription Details Modal ── */}
      {selectedSub && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl border border-border space-y-4 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <h3 className="text-base font-bold text-foreground">{selectedSub.name}</h3>
              <button onClick={() => setSelectedSub(null)} className="text-muted-foreground hover:text-foreground">
                <X className="size-5" />
              </button>
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between rounded-xl bg-blue-500/5 p-3">
                <span className="text-xs font-bold text-blue-600">مدة الاشتراك:</span>
                <span className="text-sm font-extrabold text-blue-600">{selectedSub.duration_days} يوم</span>
              </div>

              {selectedSub.description && (
                <p className="text-xs text-muted-foreground leading-relaxed">{selectedSub.description}</p>
              )}

              {selectedSub.courses && selectedSub.courses.length > 0 && (
                <div className="space-y-1.5">
                  <h4 className="text-xs font-bold text-foreground">الكورسات المشمولة:</h4>
                  <div className="max-h-32 overflow-y-auto rounded-xl border border-border divide-y divide-border">
                    {selectedSub.courses.map((c) => (
                      <div key={c.id} className="p-2.5 text-xs text-foreground flex items-center gap-2">
                        <Check className="size-3.5 text-emerald-500 shrink-0" />
                        <span className="truncate">{c.fullname}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <div className="flex items-center justify-between rounded-xl bg-muted/40 p-3 text-xs">
                <span className="text-muted-foreground">سعر الفرد:</span>
                <div>
                  {selectedSub.offer ? (
                    <div className="flex items-center gap-1.5">
                      <span className="text-muted-foreground line-through">
                        {selectedSub.offer.original} جنيه
                      </span>
                      <span className="font-bold text-foreground">{selectedSub.offer.final} جنيه</span>
                    </div>
                  ) : (
                    <span className="font-bold text-foreground">{selectedSub.price} جنيه</span>
                  )}
                </div>
              </div>
            </div>

            {/* Buttons inside details modal */}
            <div className="flex items-center gap-2 pt-2">
              <button
                type="button"
                disabled={Boolean(activeSubscription) || loadingId === `sub-${selectedSub.id}-normal`}
                onClick={() => handleSubscriptionBuy(selectedSub.id, "normal")}
                className={cn(
                  "flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50",
                  ownedB2bSubIds.has(selectedSub.id) || Number(selectedSub.b2b_enabled) === 1
                    ? "flex-1"
                    : "w-full",
                )}
              >
                {loadingId === `sub-${selectedSub.id}-normal` ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : activeSubscription ? (
                  "لديك اشتراك نشط"
                ) : (
                  "اشتراك فردي"
                )}
              </button>

              {ownedB2bSubIds.has(selectedSub.id) ? (
                <Link
                  href="/subscriptions?tab=b2b"
                  className="flex-1 flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-4 py-2.5 text-xs font-bold text-emerald-600 transition hover:bg-emerald-500/10"
                >
                  <Settings2 className="size-4" />
                  إدارة اشتراك B2B
                </Link>
              ) : Number(selectedSub.b2b_enabled) === 1 ? (
                <button
                  type="button"
                  onClick={() => {
                    setB2bSub(selectedSub);
                    setSelectedSub(null);
                  }}
                  className="flex-1 flex items-center justify-center gap-1.5 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5 text-xs font-bold text-primary transition hover:bg-primary/10"
                >
                  <Building2 className="size-4" />
                  اشتراك B2B
                </button>
              ) : null}
            </div>
          </div>
        </div>
      )}

      {/* ── B2B Seats Selection Modal ── */}
      {b2bSub && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl border border-border space-y-4">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <div className="flex items-center gap-2">
                <Building2 className="size-5 text-primary" />
                <h3 className="text-base font-bold text-foreground">اشتراك شركات (B2B) - {b2bSub.name}</h3>
              </div>
              <button onClick={() => setB2bSub(null)} className="text-muted-foreground hover:text-foreground">
                <X className="size-5" />
              </button>
            </div>

            <p className="text-xs text-muted-foreground">
              اختر عدد المقاعد المطلوبة لشركتك أو فريقك للاستفادة من خصومات الحجم:
            </p>

            {b2bSub.seat_options && b2bSub.seat_options.length > 0 ? (
              <div className="grid gap-2">
                {b2bSub.seat_options.map((option) => {
                  const isSelected = selectedSeats === option.seats;
                  return (
                    <button
                      key={option.seats}
                      type="button"
                      onClick={() => setSelectedSeats(option.seats)}
                      className={`flex items-center justify-between rounded-xl border p-3 text-xs transition ${
                        isSelected
                          ? "border-primary bg-primary/10 text-primary font-bold"
                          : "border-border bg-background text-foreground hover:bg-muted"
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        <UsersIcon className="size-4" />
                        <span>{option.seats} مقعد (حساب)</span>
                      </div>
                      {option.discount_percent > 0 && (
                        <span className="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold text-emerald-600">
                          خصم {option.discount_percent}%
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            ) : (
              <div className="space-y-2">
                <label className="text-xs font-bold text-foreground">عدد المقاعد:</label>
                <input
                  type="number"
                  min={2}
                  max={500}
                  value={selectedSeats}
                  onChange={(e) => setSelectedSeats(Math.max(1, parseInt(e.target.value) || 1))}
                  className="w-full rounded-xl border border-border bg-background p-2.5 text-xs text-foreground outline-none focus:ring-1 focus:ring-primary"
                />
              </div>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setB2bSub(null)}
                className="rounded-xl px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted"
              >
                إلغاء
              </button>
              <button
                type="button"
                disabled={loadingId === `sub-${b2bSub.id}-b2b`}
                onClick={() => handleSubscriptionBuy(b2bSub.id, "b2b", selectedSeats)}
                className="flex items-center gap-2 rounded-xl bg-primary px-5 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
              >
                {loadingId === `sub-${b2bSub.id}-b2b` ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : (
                  `إتمام الدفع (${selectedSeats} مقعد)`
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function UsersIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
  );
}
