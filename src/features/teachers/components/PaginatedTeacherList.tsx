"use client";

import { useState } from "react";
import Link from "next/link";
import { TeacherCard } from "./TeacherCard";
import { Pagination } from "@/components/ui/Pagination";
import type { Teacher } from "../types";

interface PaginatedTeacherListProps {
  teachers: Teacher[];
  locale?: string;
  pageSize?: number;
}

export function PaginatedTeacherList({
  teachers,
  locale = "ar",
  pageSize = 12,
}: PaginatedTeacherListProps) {
  const [currentPage, setCurrentPage] = useState(1);

  const totalPages = Math.ceil(teachers.length / pageSize);
  const currentTeachers = teachers.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {currentTeachers.map((teacher) => (
          <Link
            key={teacher.userid}
            href={`/teachers/${teacher.userid}`}
            className="block transition-transform hover:-translate-y-0.5"
          >
            <TeacherCard teacher={teacher} locale={locale} />
          </Link>
        ))}
      </div>

      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={teachers.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />
    </div>
  );
}
