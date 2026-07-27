import type { Offer } from "@/types/api";
import type { JitsiSession } from "@/features/lessons/types";

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

/** Returned by local_payments_get_course_price */
export interface CoursePrice {
  courseid: number;
  country: string;
  currency: string;
  price: number;
  original_price: number;
  sale_price?: number;
  is_sale_active?: boolean;
  discount_percentage?: number;
  sale_ends_at?: number;
  is_free?: boolean;
  is_enrolled?: boolean;
  is_purchased?: boolean;
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
  // Extra fields from getalltopics.php
  fileurl?: string;
  resourcetype?: string;
  locked?: boolean;
  availabilityinfo?: string;
}

export interface CourseSection {
  id: number;
  name: string;
  summary?: string;
  visible?: number;
  uservisible?: boolean;
  modules: CourseModule[];
}

/** Raw activity shape from /local/multitopics/getalltopics.php */
export interface RawActivity {
  id: string;
  instance?: string;
  modname: string;
  name: string;
  sectionnum?: string | number;
  visible?: boolean;
  uservisible?: boolean;
  url?: string;
  modicon?: string;
  resourcetype?: string;
  fileurl?: string;
  locked?: boolean;
  availabilityinfo?: string;
  jitsi_session?: JitsiSession | null;
}

export interface RawTopicSection {
  id: string;
  sectionnum: number;
  name: string;
  activities: RawActivity[];
}

export interface RawParentSection {
  id: string;
  sectionnum: number;
  name: string;
  parent?: boolean;
  activities: RawActivity[];
  topics?: RawTopicSection[];
}

export interface RawCourseTopics {
  courseid: number;
  fullname?: string;
  format?: string;
  isavailable?: boolean;
  status?: string;
  other_fields?: Record<string, unknown>;
  parents: RawParentSection[];
}