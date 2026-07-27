import type { Metadata } from "next";
import { Users } from "lucide-react";
import { getTeachers } from "@/features/teachers/server";
import { TeacherSearch } from "@/features/teachers/components/TeacherSearch";
import { PaginatedTeacherList } from "@/features/teachers/components/PaginatedTeacherList";

export const metadata: Metadata = { title: "المدرسون" };

interface TeachersPageProps {
  searchParams: Promise<{ search?: string }>;
}

export default async function TeachersPage({
  searchParams,
}: TeachersPageProps) {
  const params = await searchParams;
  const search = params.search?.trim() ?? "";
  const locale = "ar";

  const { teachers, total } = await getTeachers({ search });

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Users className="size-6 text-primary" />
          <h1 className="text-h1 font-bold">المدرسون</h1>
          {total > 0 && (
            <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
              {total}
            </span>
          )}
        </div>

        <TeacherSearch defaultValue={search} locale={locale} />
      </div>

      {teachers.length === 0 ? (
        <div className="flex min-h-[300px] items-center justify-center rounded-2xl border border-border bg-card text-caption text-muted-foreground">
          {search
            ? `لا يوجد مدرسون يطابقون "${search}"`
            : "لا يوجد مدرسون حالياً."}
        </div>
      ) : (
        <PaginatedTeacherList teachers={teachers} locale={locale} pageSize={12} />
      )}
    </div>
  );
}
