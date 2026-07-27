"use client";

import { useState } from "react";
import Link from "next/link";
import { GraduationCap, Play, CheckCircle2, Flag, Calendar, Loader2, BookOpen } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { CatalogueProgram, MyProgram } from "../types";
import { Pagination } from "@/components/ui/Pagination";
import { joinFreeProgram } from "../actions";
import { BuyProgramModal } from "./BuyProgramModal";

function formatDate(ts: number): string {
  if (!ts) return "غير محدد";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}

// ── My Programs tab ──────────────────────────────────────────────────────────

function MyProgramsTab({ programs, pageSize = 6 }: { programs: MyProgram[]; pageSize?: number }) {
  const [currentPage, setCurrentPage] = useState(1);

  if (programs.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <GraduationCap className="size-8 opacity-30" />
        <p className="text-caption">لا توجد برامج مشترك فيها</p>
      </div>
    );
  }

  const totalPages = Math.ceil(programs.length / pageSize);
  const currentPrograms = programs.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2">
        {currentPrograms.map((prog) => {
          const isCompleted = prog.completed === 1 || prog.timecompleted > 0;

          return (
            <div
              key={prog.id}
              className="rounded-2xl border border-border bg-card p-5 shadow-sm flex flex-col justify-between gap-4 transition hover:border-primary/50"
            >
              <div className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                  <span
                    className={cn(
                      "rounded-full px-2.5 py-0.5 text-[11px] font-bold",
                      isCompleted
                        ? "bg-emerald-500/10 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"
                        : "bg-primary/10 text-primary"
                    )}
                  >
                    {isCompleted ? "مكتمل" : "قيد التقدم"}
                  </span>
                  <span className="text-small font-bold text-foreground">{prog.name}</span>
                </div>

                <div className="space-y-1.5 text-xs text-muted-foreground">
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-1.5">
                      <Play className="size-3.5 shrink-0 text-primary" />
                      <span>بدأ: {formatDate(prog.timestart)}</span>
                    </div>
                  </div>

                  {isCompleted ? (
                    <div className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-semibold">
                      <CheckCircle2 className="size-3.5 shrink-0" />
                      <span>اكتمل: {formatDate(prog.timecompleted)}</span>
                    </div>
                  ) : (
                    <div className="flex flex-wrap items-center justify-between gap-2 pt-1 text-[11px]">
                      <div className="flex items-center gap-1">
                        <Calendar className="size-3 shrink-0 text-amber-500" />
                        <span>الاستحقاق: {prog.timedue > 0 ? formatDate(prog.timedue) : "غير محدد"}</span>
                      </div>
                      <div className="flex items-center gap-1">
                        <Flag className="size-3 shrink-0 text-rose-500" />
                        <span>ينتهي: {prog.timeend > 0 ? formatDate(prog.timeend) : "غير محدد"}</span>
                      </div>
                    </div>
                  )}
                </div>
              </div>

              <div className="border-t border-border pt-3 flex justify-end">
                <Link
                  href={`/programs/${prog.id}`}
                  className="w-full text-center rounded-xl bg-primary px-4 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90"
                >
                  تصفح التفاصيل والمحتوى
                </Link>
              </div>
            </div>
          );
        })}
      </div>

      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={programs.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />
    </div>
  );
}

// ── Catalogue Programs tab ───────────────────────────────────────────────────

function CatalogueProgramsTab({
  programs,
  isLoggedIn,
  pageSize = 6,
}: {
  programs: CatalogueProgram[];
  isLoggedIn: boolean;
  pageSize?: number;
}) {
  const [currentPage, setCurrentPage] = useState(1);
  const [loadingId, setLoadingId] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [buyProgram, setBuyProgram] = useState<CatalogueProgram | null>(null);

  if (programs.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <GraduationCap className="size-8 opacity-30" />
        <p className="text-caption">لا توجد برامج متاحة حالياً</p>
      </div>
    );
  }

  const totalPages = Math.ceil(programs.length / pageSize);
  const currentPrograms = programs.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  async function handleProgramJoin(prog: CatalogueProgram) {
    if (!isLoggedIn) {
      window.location.href = "/login";
      return;
    }
    setLoadingId(`prog-${prog.id}`);
    setActionError(null);

    const res = await joinFreeProgram(prog.id);
    setLoadingId(null);
    if (res.success) {
      window.location.reload();
    } else {
      setActionError(res.error || "تعذّر الانضمام للبرنامج");
    }
  }

  return (
    <div className="space-y-4">
      {actionError && (
        <div className="rounded-2xl bg-destructive/10 p-3 text-xs font-semibold text-destructive">
          {actionError}
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {currentPrograms.map((prog) => (
          <div
            key={prog.id}
            className="rounded-2xl border border-border bg-card p-5 shadow-sm flex flex-col justify-between gap-4 transition hover:border-primary/50 hover:shadow-md"
          >
            <div className="space-y-3">
              <div className="flex items-start justify-between gap-2">
                <div className="flex flex-wrap items-center gap-1.5">
                  {prog.owned ? (
                    <span className="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold text-emerald-600">
                      ملتحق
                    </span>
                  ) : prog.free === 1 ? (
                    <span className="rounded-full bg-blue-500/10 px-2.5 py-0.5 text-[10px] font-bold text-blue-600">
                      مجاني
                    </span>
                  ) : (
                    <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-bold text-primary">
                      برنامج مدفوع
                    </span>
                  )}

                  {prog.offer && !prog.owned && (
                    <span className="rounded-full bg-destructive/10 px-2 py-0.5 text-[10px] font-bold text-destructive">
                      {prog.offer.label}
                    </span>
                  )}
                </div>

                <h3 className="font-bold text-end text-foreground text-small line-clamp-1">{prog.name}</h3>
              </div>

              {prog.description && (
                <p className="text-xs text-muted-foreground text-end line-clamp-2">{prog.description}</p>
              )}
            </div>

            <div className="space-y-3 border-t border-border pt-3">
              <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">السعر:</span>
                <div>
                  {prog.free === 1 ? (
                    <span className="font-bold text-emerald-600">مجاناً</span>
                  ) : prog.offer ? (
                    <div className="flex items-center gap-1.5">
                      <span className="text-[11px] text-muted-foreground line-through">
                        {prog.offer.original} {prog.currency || "EGP"}
                      </span>
                      <span className="font-extrabold text-foreground">
                        {prog.offer.final} {prog.currency || "EGP"}
                      </span>
                    </div>
                  ) : (
                    <span className="font-extrabold text-foreground">
                      {prog.price} {prog.currency || "EGP"}
                    </span>
                  )}
                </div>
              </div>

              {/* Action Buttons: Main action ("اشترك الآن" / "انضمام") FIRST (on the right in RTL) */}
              <div className="flex items-center gap-2">
                {!prog.owned ? (
                  <>
                    <button
                      type="button"
                      onClick={() => {
                        if (!isLoggedIn) {
                          window.location.href = "/login";
                          return;
                        }
                        if (prog.free === 1) {
                          handleProgramJoin(prog);
                        } else {
                          setBuyProgram(prog);
                        }
                      }}
                      disabled={loadingId === `prog-${prog.id}`}
                      className="flex-1 flex items-center justify-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-bold text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50 cursor-pointer"
                    >
                      {loadingId === `prog-${prog.id}` ? (
                        <Loader2 className="size-3.5 animate-spin" />
                      ) : prog.free === 1 ? (
                        "انضمام"
                      ) : (
                        "اشترك الآن"
                      )}
                    </button>
                    <Link
                      href={`/programs/${prog.id}`}
                      className="flex-1 text-center rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-bold text-foreground transition hover:bg-muted"
                    >
                      تصفح
                    </Link>
                  </>
                ) : (
                  <Link
                    href={`/programs/${prog.id}`}
                    className="w-full text-center rounded-xl bg-primary px-3.5 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90"
                  >
                    تصفح التفاصيل والمحتوى
                  </Link>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>

      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={programs.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />

      {buyProgram && (
        <BuyProgramModal
          programId={buyProgram.id}
          programName={buyProgram.name}
          basePrice={buyProgram.offer ? Number(buyProgram.offer.final) : Number(buyProgram.price)}
          currency={buyProgram.currency || "EGP"}
          open={Boolean(buyProgram)}
          onClose={() => setBuyProgram(null)}
        />
      )}
    </div>
  );
}

// ── Page root ────────────────────────────────────────────────────────────────

const TABS = [
  { id: "mine", label: "برامجي" },
  { id: "catalogue", label: "البرامج" },
] as const;

type Tab = (typeof TABS)[number]["id"];

interface ProgramsPageClientProps {
  myPrograms: MyProgram[];
  cataloguePrograms: CatalogueProgram[];
  isLoggedIn?: boolean;
}

export function ProgramsPageClient({ myPrograms, cataloguePrograms, isLoggedIn = true }: ProgramsPageClientProps) {
  const [activeTab, setActiveTab] = useState<Tab>(myPrograms.length > 0 ? "mine" : "catalogue");

  return (
    <div className="space-y-4">
      {/* Tabs */}
      <div className="flex border-b border-border">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={cn(
              "flex-1 py-2.5 text-small font-medium transition-colors",
              activeTab === tab.id
                ? "border-b-2 border-primary text-primary"
                : "text-muted-foreground hover:text-foreground"
            )}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Tab content */}
      {activeTab === "mine" && <MyProgramsTab programs={myPrograms} />}
      {activeTab === "catalogue" && (
        <CatalogueProgramsTab programs={cataloguePrograms} isLoggedIn={isLoggedIn} />
      )}
    </div>
  );
}
