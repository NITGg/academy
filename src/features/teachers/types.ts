export interface TeacherSubject {
  subject: string;
  specialization?: string;
}

export interface TeacherHour {
  dayofweek: number; // 0 = Sunday … 6 = Saturday (same as JS Date.getDay())
  starttime: string; // "HH:MM"
  endtime: string;   // "HH:MM"
}

export interface Teacher {
  userid: number;
  fullname: string;
  email?: string;
  headline?: string;
  bio?: string;
  experience?: string;
  photourl?: string;
  rating?: number;
  approved?: number;
  available?: number;
  subjects?: TeacherSubject[];
  hours?: TeacherHour[];
  busy_times?: [number, number][];
}

export interface TeachersResponse {
  total: number;
  page: number;
  perpage: number;
  teachers: Teacher[];
}

export interface LessonSettings {
  min_booking_minutes: number;
  cancel_deadline_minutes: number;
  update_deadline_minutes: number;
  start_allowed_minutes: number;
  absence_report_minutes: number;
}
