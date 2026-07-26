export interface CalendarEvent {
  id: number;
  name: string;
  eventtype: "due" | "site" | "course" | "user" | "category" | string;
  timestart: number;
  modulename?: string;
  courseid?: number;
  url?: string;
  course?: { id: number; fullname: string };
}

export interface CalendarDay {
  mday: number;
  timestamp: number;
  istoday: boolean;
  isweekend: boolean;
  events: CalendarEvent[];
}

export interface CalendarWeek {
  prepadding: number[];
  postpadding: number[];
  days: CalendarDay[];
}

export interface CalendarMonthView {
  periodname: string;
  previousperiod: { year: number; mon: number };
  nextperiod: { year: number; mon: number };
  date: { year: number; mon: number };
  daynames: { shortname: string }[];
  weeks: CalendarWeek[];
}
