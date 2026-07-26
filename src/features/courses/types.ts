import type { Offer } from "@/types/api";

export interface CourseCategory {
  id: number;
  name: string;
  description?: string;
  parent?: number;
  coursecount?: number;
}

export interface Course {
  id: number;
  fullname: string;
  shortname: string;
  categoryid: number;
  categoryname?: string;
  summary?: string;
  courseimage?: string;
  format?: string;
  startdate?: number;
  enddate?: number;
  enrolledusercount?: number;
  teacherName?: string;
  teacherImage?: string;
  price?: number;
  isFree?: boolean;
  isEnrolled?: boolean;
  offer?: Offer;
}

export interface CourseModule {
  id: number;
  name: string;
  modname: "resource" | "assign" | "quiz" | "url" | "forum" | "page" | string;
  instance?: number;
  url?: string;
  visible?: number;
  uservisible?: boolean;
  completiondata?: {
    state: number; // 0 = incomplete, 1 = complete
  };
}

export interface CourseSection {
  id: number;
  name: string;
  summary?: string;
  visible?: number;
  uservisible?: boolean;
  modules: CourseModule[];
}