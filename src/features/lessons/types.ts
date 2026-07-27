export interface JitsiRecording {
  id: number;
  title: string;
  status: string;
  playback_url?: string;
  thumbnail_url?: string;
  embed_url?: string;
  timecreated?: number;
}

export type JitsiFeatureFlags = Record<string, boolean | undefined>;

export interface JitsiSession {
  server_url?: string;
  room?: string;
  jwt?: string;
  subject?: string;
  is_teacher?: boolean;
  available: boolean;
  available_info?: string;
  whiteboard_url?: string;
  feature_flags?: JitsiFeatureFlags;
  recordings?: JitsiRecording[];
}

export interface LessonProposal {
  proposed_time: number;
  proposed_by: string; // "teacher" | "student"
  status: string; // "pending" | ...
}

export interface Lesson {
  id: number;
  subject: string;
  status: string;
  my_role: string;
  requested_time?: number;
  confirmed_time?: number;
  suggested_time?: number;
  effective_time?: number;
  actual_start?: number;
  actual_end?: number;
  note?: string;
  reject_reason?: string | null;
  cancel_reason?: string | null;
  flex_state?: string; // none | reserved | consumed | returned
  teacherid?: number;
  studentid?: number;
  teacher_name?: string;
  student_name?: string;
  teacher_photo?: string;
  actions: string[];
  proposals?: LessonProposal[];
  can_join: boolean;
  join_url?: string;
  cmid?: number;
  jitsi_session?: JitsiSession | null;
}

export interface FlexTx {
  id: number;
  type: string;
  amount: number;
  balance: number;
  lessonid?: number;
  note?: string;
  timecreated: number;
}
