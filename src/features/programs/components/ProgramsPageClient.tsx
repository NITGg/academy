"use client";

import { useState } from "react";
import { GraduationCap, Play, CheckCircle2, Flag, Calendar } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { CatalogueProgram, MyProgram } from "../types";

function formatDate(ts: number): string {
  if (!ts) return "غير محدد";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}

// ── My Programs tab ──────────────────────────────────────────────────────────

function MyProgramsTab({ programs }: { programs: MyProgram[] }) {
  if (programs.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <GraduationCap className="size-8 opacity-30" />
        <p className="text-caption">لا توجد برامج مشترك فيها</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {programs.map((prog) => {
        const isCompleted = prog.completed === 1 || prog.timecompleted > 0;

        return (
          <div key={prog.id} className="rounded-2xl border border-border bg-card p-4 shadow-sm space-y-3">
            <div className="flex items-center justify-between gap-2">
              <span
                className={cn(
                  "rounded-full px-2.5 py-0.5 text-[11px] font-semibold",
                  isCompleted
                    ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                    : "bg-muted text-muted-foreground"
                )}
              >
                {isCompleted ? "مكتمل" : "قيد التقدم"}
              </span>
              <span className="text-caption font-bold text-foreground">{prog.name}</span>
            </div>

            <div className="space-y-1.5 text-[11px] text-muted-foreground">
              <div className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-1.5">
                  <Play className="size-3.5 shrink-0" />
                  <span>بدأ: {formatDate(prog.timestart)}</span>
                </div>
              </div>

              {isCompleted ? (
                <div className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                  <CheckCircle2 className="size-3.5 shrink-0" />
                  <span>اكتمل: {formatDate(prog.timecompleted)}</span>
                </div>
              ) : (
                <>
                  <div className="flex items-center gap-1.5">
                    <Calendar className="size-3.5 shrink-0" />
                    <span>الاستحقاق: {prog.timedue > 0 ? formatDate(prog.timedue) : "غير محدد"}</span>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Flag className="size-3.5 shrink-0" />
                    <span>ينتهي: {prog.timeend > 0 ? formatDate(prog.timeend) : "غير محدد"}</span>
                  </div>
                </>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ── Catalogue Programs tab ───────────────────────────────────────────────────

function CatalogueProgramsTab({ programs }: { programs: CatalogueProgram[] }) {
  if (programs.length === 0) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <GraduationCap className="size-8 opacity-30" />
        <p className="text-caption">لا توجد برامج متاحة حالياً</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {programs.map((prog) => (
        <div key={prog.id} className="rounded-2xl border border-border bg-card p-4 shadow-sm space-y-3">
          <div className="flex items-center justify-between gap-2">
            <span className="rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
              {prog.owned ? "مشترك" : prog.joinable ? "فلتحق" : "متاح"}
            </span>
            <span className="text-caption font-bold text-foreground">{prog.name}</span>
          </div>

          <Button variant="default" size="lg" className="w-full rounded-xl">
            فتح
          </Button>
        </div>
      ))}
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
}

export function ProgramsPageClient({ myPrograms, cataloguePrograms }: ProgramsPageClientProps) {
  const [activeTab, setActiveTab] = useState<Tab>("mine");

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
      {activeTab === "catalogue" && <CatalogueProgramsTab programs={cataloguePrograms} />}
    </div>
  );
}
