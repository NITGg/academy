export interface TeacherSubject {
  subject: string;
  specialization?: string;
}

export interface TeacherHour {
  dayofweek: number; // 0 = Sunday
  starttime: string;
  endtime: string;
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
