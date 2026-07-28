"use client";

import { useState } from "react";
import Link from "next/link";
import Image from "next/image";
import {
  GraduationCap,
  Calendar,
  CheckCircle2,
  Play,
  Flag,
  BookOpen,
  Award,
  Loader2,
  ChevronLeft,
  Lock,
  Layers,
  Sparkles,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn, getAppUrl } from "@/lib/utils";
import type { ProgramDetails, ProgramCertificate, ProgramContentItem } from "../types";
import { joinFreeProgram } from "../actions";
import { BuyProgramModal } from "./BuyProgramModal";
import { CertificateViewer } from "@/features/activity/components/CertificateViewer";

interface ProgramDetailsClientProps {
  program: ProgramDetails;
  certificates: ProgramCertificate[];
  isLoggedIn: boolean;
}

function formatDate(ts?: number): string {
  if (!ts) return "غير محدد";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}

function ProgramContentTree({ items }: { items: ProgramContentItem[] }) {
  if (!items || items.length === 0) return null;

  return (
    <div className="space-y-3">
      {items.map((item) => {
        const isCompleted = item.completed === 1 || item.timecompleted > 0;
        const isSet = item.type === "set";

        return (
          <div
            key={item.itemid}
            className={cn(
              "rounded-2xl border border-border bg-card p-4 shadow-sm transition-all",
              isSet ? "bg-muted/20 border-primary/20" : "",
            )}
          >
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                {isSet ? (
                  <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Layers className="size-5" />
                  </div>
                ) : (
                  <div
                    className={cn(
                      "flex size-9 shrink-0 items-center justify-center rounded-xl",
                      isCompleted
                        ? "bg-emerald-500/10 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"
                        : "bg-primary/10 text-primary",
                    )}
                  >
                    {isCompleted ? (
                      <CheckCircle2 className="size-5" />
                    ) : (
                      <BookOpen className="size-5" />
                    )}
                  </div>
                )}
                <div>
                  <h4 className="text-small font-bold text-foreground">
                    {item.name}
                  </h4>
                  {item.sequencetype && (
                    <span className="text-[11px] text-muted-foreground">
                      الترتيب: {item.sequencetype}
                    </span>
                  )}
                </div>
              </div>

              {!isSet && item.courseid > 0 && (
                <Link
                  href={`/courses/${item.courseid}`}
                  className="flex items-center gap-1.5 rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/20"
                >
                  <span>عرض الكورس</span>
                  <ChevronLeft className="size-3.5" />
                </Link>
              )}
            </div>

            {item.children && item.children.length > 0 && (
              <div className="mt-3 border-t border-border/60 pt-3 space-y-2 pr-4 border-r-2 border-r-primary/20">
                <ProgramContentTree items={item.children} />
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

export function ProgramDetailsClient({
  program,
  certificates,
  isLoggedIn,
}: ProgramDetailsClientProps) {
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [buyModalOpen, setBuyModalOpen] = useState(false);
  const [activeCertModal, setActiveCertModal] =
    useState<ProgramCertificate | null>(null);

  const isOwned = program.owned === 1 || Boolean(program.allocation);
  const isCompleted =
    program.allocation?.completed === 1 ||
    (program.allocation?.timecompleted ?? 0) > 0;

  async function handleJoinOrBuy() {
    if (!isLoggedIn) {
      window.location.assign(getAppUrl("/login"));
      return;
    }
    setErrorMessage(null);

    if (program.free === 1 && program.joinable === 1) {
      setLoading(true);
      const res = await joinFreeProgram(program.id);
      setLoading(false);
      if (res.success) {
        window.location.reload();
      } else {
        setErrorMessage(res.error || "تعذّر الانضمام إلى البرنامج");
      }
    } else {
      setBuyModalOpen(true);
    }
  }

  function handleOpenCertificate(cert: ProgramCertificate) {
    if (!isLoggedIn) {
      window.location.assign(getAppUrl("/login"));
      return;
    }
    setActiveCertModal(cert);
  }

  return (
    <div className="space-y-6 pb-12">
      {/* Top back navigation */}
      <div>
        <Link
          href="/programs"
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground hover:text-foreground transition-colors"
        >
          <ChevronLeft className="size-4 rotate-180" />
          <span>العودة للبرامج</span>
        </Link>
      </div>

      {/* Hero Card */}
      <div className="relative overflow-hidden rounded-3xl border border-border bg-card p-6 shadow-md md:p-8 space-y-6">
        <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
          <div className="flex items-start gap-4">
            <div className="relative size-20 shrink-0 overflow-hidden rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center">
              {program.image ? (
                <Image
                  src={program.image}
                  alt={program.name}
                  fill
                  sizes="80px"
                  className="object-cover"
                  unoptimized
                />
              ) : (
                <GraduationCap className="size-10 text-primary" />
              )}
            </div>

            <div className="space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                {isOwned ? (
                  <span className="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                    {isCompleted ? "مكتمل" : "ملتحق"}
                  </span>
                ) : program.free === 1 ? (
                  <span className="rounded-full bg-blue-500/10 px-3 py-1 text-xs font-bold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                    مجاني
                  </span>
                ) : (
                  <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                    برنامج مدفوع
                  </span>
                )}

                {program.offer && !isOwned && (
                  <span className="rounded-full bg-destructive/10 px-2.5 py-0.5 text-xs font-bold text-destructive">
                    {program.offer.label}
                  </span>
                )}
              </div>

              <h1 className="text-xl font-bold text-foreground md:text-2xl">
                {program.name}
              </h1>
            </div>
          </div>

          {/* Pricing & CTA */}
          <div className="flex flex-col items-start md:items-end gap-3 border-t md:border-t-0 border-border pt-4 md:pt-0">
            {!isOwned && (
              <div>
                {program.free === 1 ? (
                  <span className="text-lg font-bold text-emerald-600">
                    مجاناً
                  </span>
                ) : program.offer ? (
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground line-through">
                      {program.offer.original} {program.currency || "EGP"}
                    </span>
                    <span className="text-xl font-extrabold text-foreground">
                      {program.offer.final} {program.currency || "EGP"}
                    </span>
                  </div>
                ) : (
                  <span className="text-xl font-extrabold text-foreground">
                    {program.price} {program.currency || "EGP"}
                  </span>
                )}
              </div>
            )}

            {!isOwned ? (
              <Button
                onClick={handleJoinOrBuy}
                disabled={loading}
                className="w-full md:w-auto rounded-2xl px-6 py-2.5 font-bold cursor-pointer"
              >
                {loading ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : program.free === 1 ? (
                  "انضمام مجاناً"
                ) : (
                  "اشترك الآن"
                )}
              </Button>
            ) : (
              <div className="flex items-center gap-2 rounded-2xl bg-emerald-500/10 px-4 py-2 text-xs font-bold text-emerald-600">
                <CheckCircle2 className="size-4" />
                <span>أنت ملتحق بهذا البرنامج</span>
              </div>
            )}
          </div>
        </div>

        {/* Description */}
        {program.description && (
          <div className="border-t border-border pt-4 text-xs text-muted-foreground leading-relaxed">
            {program.description}
          </div>
        )}

        {/* Error notification */}
        {errorMessage && (
          <div className="rounded-2xl bg-destructive/10 p-3 text-xs font-semibold text-destructive">
            {errorMessage}
          </div>
        )}
      </div>

      {/* User Allocation Info (If Enrolled) */}
      {program.allocation && (
        <div className="rounded-3xl border border-border bg-card p-5 shadow-sm space-y-3">
          <h3 className="text-small font-bold text-foreground flex items-center gap-2">
            <Sparkles className="size-4 text-primary" />
            <span>بيانات الاشتراك في البرنامج</span>
          </h3>

          <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 text-xs">
            <div className="rounded-2xl bg-muted/40 p-3 space-y-1">
              <span className="text-muted-foreground flex items-center gap-1">
                <Play className="size-3.5 text-primary" />
                تاريخ البدء
              </span>
              <p className="font-bold text-foreground">
                {formatDate(program.allocation.timestart)}
              </p>
            </div>

            <div className="rounded-2xl bg-muted/40 p-3 space-y-1">
              <span className="text-muted-foreground flex items-center gap-1">
                <Calendar className="size-3.5 text-amber-500" />
                تاريخ الاستحقاق
              </span>
              <p className="font-bold text-foreground">
                {program.allocation.timedue > 0
                  ? formatDate(program.allocation.timedue)
                  : "غير محدد"}
              </p>
            </div>

            <div className="rounded-2xl bg-muted/40 p-3 space-y-1">
              <span className="text-muted-foreground flex items-center gap-1">
                <Flag className="size-3.5 text-rose-500" />
                تاريخ الانتهاء
              </span>
              <p className="font-bold text-foreground">
                {program.allocation.timeend > 0
                  ? formatDate(program.allocation.timeend)
                  : "غير محدد"}
              </p>
            </div>

            <div className="rounded-2xl bg-muted/40 p-3 space-y-1">
              <span className="text-muted-foreground flex items-center gap-1">
                <CheckCircle2 className="size-3.5 text-emerald-500" />
                حالة الإكمال
              </span>
              <p className="font-bold text-foreground">
                {isCompleted
                  ? formatDate(program.allocation.timecompleted)
                  : "قيد التقدم"}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Program Hierarchy / Content Structure */}
      <div className="space-y-4">
        <h3 className="text-base font-bold text-foreground flex items-center gap-2">
          <BookOpen className="size-5 text-primary" />
          <span>محتويات البرنامج والكورسات</span>
        </h3>

        {program.content && program.content.length > 0 ? (
          <ProgramContentTree items={program.content} />
        ) : (
          <div className="flex min-h-[140px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
            <BookOpen className="size-6 opacity-30" />
            <p className="text-caption">
              لا توجد محتويات مسجلة لهذا البرنامج حالياً
            </p>
          </div>
        )}
      </div>

      {/* Certificate Section */}
      {certificates && certificates.length > 0 && (
        <div className="space-y-4">
          <h3 className="text-base font-bold text-foreground flex items-center gap-2">
            <Award className="size-5 text-amber-500" />
            <span>شهادات البرنامج</span>
          </h3>

          <div className="grid gap-4 sm:grid-cols-2">
            {certificates.map((cert) => (
              <div
                key={cert.certificateid}
                className="rounded-2xl border border-border bg-card p-5 shadow-sm space-y-4"
              >
                <div className="flex items-center justify-between">
                  <span className="rounded-full bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-bold text-amber-600">
                    {cert.openable ? "متاحة الآن" : "غير مكتمل الشروط"}
                  </span>
                  <h4 className="text-small font-bold text-foreground">
                    {cert.name}
                  </h4>
                </div>

                {/* Progress requirements */}
                {cert.results && cert.results.length > 0 && (
                  <div className="space-y-2 border-t border-border pt-3">
                    <p className="text-xs font-semibold text-muted-foreground">
                      متطلبات الحصول على الشهادة:
                    </p>
                    {cert.results.map((res, idx) => (
                      <div
                        key={idx}
                        className="flex items-center justify-between text-xs"
                      >
                        <span className="text-muted-foreground flex items-center gap-1.5">
                          {res.passed ? (
                            <CheckCircle2 className="size-3.5 text-emerald-500 shrink-0" />
                          ) : (
                            <Lock className="size-3.5 text-muted-foreground shrink-0" />
                          )}
                          {res.label}
                        </span>
                        <span className="font-bold text-foreground">
                          {res.actual} / {res.required} {res.unit}
                        </span>
                      </div>
                    ))}
                  </div>
                )}

                <Button
                  onClick={() => handleOpenCertificate(cert)}
                  disabled={!cert.openable}
                  variant={cert.openable ? "default" : "outline"}
                  className="w-full rounded-xl gap-2 text-xs font-bold cursor-pointer"
                >
                  <Award className="size-3.5" />
                  <span>عرض الشهادة</span>
                </Button>
              </div>
            ))}
          </div>
        </div>
      )}

      {buyModalOpen && (
        <BuyProgramModal
          programId={program.id}
          programName={program.name}
          basePrice={
            program.offer ? Number(program.offer.final) : Number(program.price)
          }
          currency={program.currency || "EGP"}
          open={buyModalOpen}
          onClose={() => setBuyModalOpen(false)}
        />
      )}

      {/* Program Certificate Modal */}
      {activeCertModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
          <div className="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-3xl bg-card border border-border p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <h3 className="text-base font-bold text-foreground flex items-center gap-2">
                <Award className="size-5 text-amber-500" />
                <span>{activeCertModal.name}</span>
              </h3>
              <button
                type="button"
                onClick={() => setActiveCertModal(null)}
                className="rounded-full p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
              >
                <X className="size-5" />
              </button>
            </div>

            <CertificateViewer
              cmid={
                activeCertModal.externalref || activeCertModal.certificateid
              }
              name={activeCertModal.name}
              isArabic={true}
            />
          </div>
        </div>
      )}
    </div>
  );
}
