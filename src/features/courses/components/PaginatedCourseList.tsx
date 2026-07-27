"use client";

import { useState } from "react";
import { Pagination } from "@/components/ui/Pagination";
import { CourseCard } from "./CourseCard";
import { MyCourseCard } from "./MyCourseCard";
import type { Course } from "../types";
import type { EnrolledCourse } from "../server";

interface PaginatedCoursesProps {
  courses: Course[];
  pageSize?: number;
}

export function PaginatedCourses({ courses, pageSize = 12 }: PaginatedCoursesProps) {
  const [currentPage, setCurrentPage] = useState(1);

  const totalPages = Math.ceil(courses.length / pageSize);
  const currentCourses = courses.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {currentCourses.map((course) => (
          <CourseCard key={course.id} course={course} />
        ))}
      </div>
      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={courses.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />
    </div>
  );
}

interface PaginatedMyCoursesProps {
  courses: EnrolledCourse[];
  pageSize?: number;
}

export function PaginatedMyCourses({ courses, pageSize = 12 }: PaginatedMyCoursesProps) {
  const [currentPage, setCurrentPage] = useState(1);

  const totalPages = Math.ceil(courses.length / pageSize);
  const currentCourses = courses.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {currentCourses.map((course) => (
          <MyCourseCard key={course.id} course={course} />
        ))}
      </div>
      <Pagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={courses.length}
        pageSize={pageSize}
        onPageChange={handlePageChange}
      />
    </div>
  );
}
