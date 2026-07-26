import { apiClient } from "@/lib/axios";
import type { Course, CourseCategory, CourseSection } from "@/features/courses/types";

export interface CoursesResponse {
  categories: CourseCategory[];
  courses: Course[];
}

export interface CourseDetailResponse {
  course: Course;
  contents: CourseSection[];
}

export const courseService = {
  getCourses: (params?: { categoryId?: string; search?: string }) =>
    apiClient.get<CoursesResponse>("/courses", { params }).then((res) => res.data),

  getCourseById: (id: string | number) =>
    apiClient.get<CourseDetailResponse>(`/courses/${id}`).then((res) => res.data),
};