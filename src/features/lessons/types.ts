export interface Lesson {
  id: number;
  subject: string;
  status: string;
  my_role: string;
  requested_time?: number;
  confirmed_time?: number;
  suggested_time?: number;
  actual_start?: number;
  actual_end?: number;
  note?: string;
  teacherid?: number;
  studentid?: number;
  teacher_name?: string;
  student_name?: string;
  teacher_photo?: string;
  actions: string[];
  can_join: boolean;
  join_url?: string;
  cmid?: number;
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
