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
  // ── Pricing / offer / access (from local_payments_get_courses_with_pricing) ──
  price?: number;            // effective price to charge (sale_price if on sale, else price)
  originalPrice?: number;    // list price, for strike-through when discounted
  discountPercentage?: number;
  isSaleActive?: boolean;
  offerName?: string;        // combined offer name(s) — subtitle/tooltip
  currency?: string;
  isFree?: boolean;
  isEnrolled?: boolean;
  isPurchased?: boolean;
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

/** One completion rule as reported by getalltopics.php (e.g. "معاينة", "تلقي علامة"). */
export interface CompletionRule {
  rulename: string;
  /** 0 = not yet met, 1 = met. */
  status: number;
  /** Localised, human-readable label from Moodle (already in the requested lang). */
  description: string;
}

export interface CourseModule {
  /** This is the course-module id (cmid) — the id used everywhere to address an activity. */
  id: number;
  name: string;
  modname: "resource" | "resource2" | "testnew" | "assign" | "quiz" | "url" | "forum" | "page" | "customcert" | string;
  instance?: number;
  url?: string;
  visible?: number;
  uservisible?: boolean;
  // ── Completion (from getalltopics.php) ──────────────────────────────────────
  /** 0 = no tracking, 1 = manual (student marks done), 2 = automatic (rule-based). */
  completion?: number;
  /** 0 = incomplete, 1 = complete, 2 = complete-pass, 3 = complete-fail. */
  completionstate?: number;
  hascompletion?: boolean;
  isautomatic?: boolean;
  /** Unix ts the activity is expected to be completed by (0 = none). */
  completionexpected?: number;
  /** Per-rule breakdown driving the "للقيام به: ..." lines. */
  completiondetails?: CompletionRule[];
  /**
   * Legacy completion shape from the core_course_get_contents fallback path
   * (getalltopics is the primary source and uses the flat fields above instead).
   */
  completiondata?: { state: number };
  // ── Content (from getalltopics.php) ─────────────────────────────────────────
  /**
   * Whether this activity has a downloadable/streamable file. The actual URL is
   * NEVER sent to the client (it embeds the Moodle token) — files load through
   * the /api/activity-file proxy, addressed by courseId + cmid.
   */
  hasFile?: boolean;
  /** MIME type of the file (e.g. "application/pdf", "video/mp4"), used to pick a viewer. */
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
  // Completion fields as emitted by getalltopics.php
  completion?: number;
  completionstate?: number;
  hascompletion?: boolean;
  isautomatic?: boolean;
  completionexpected?: number;
  completiondetails?: CompletionRule[];
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