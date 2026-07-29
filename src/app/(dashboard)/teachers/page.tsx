import type { Metadata } from "next";
import { Users } from "lucide-react";
import { getTeachers } from "@/features/teachers/server";
import { TeachersPageClient } from "@/features/teachers/components/TeachersPageClient";

export const metadata: Metadata = { title: "المدرسون" };

interface TeachersPageProps {
  searchParams: Promise<{ search?: string }>;
}

export default async function TeachersPage({
  searchParams,
}: TeachersPageProps) {
  const params = await searchParams;
  const search = params.search?.trim() ?? "";

  const { teachers, total } = await getTeachers({ search });

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Users className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">المدرسون</h1>
        {total > 0 && (
          <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
            {total}
          </span>
        )}
      </div>

      <TeachersPageClient
        teachers={teachers}
        locale="ar"
        defaultSearch={search}
      />
    </div>
  );
}
