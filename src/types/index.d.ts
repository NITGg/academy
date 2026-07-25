// Re-export all domain types from feature modules.
// Add feature-specific types in src/features/<feature>/types.ts and re-export here.

export type { Locale } from "@/i18n/request";

export interface User {
  id: number;
  username: string;
  firstname: string;
  lastname: string;
  email: string;
  pictureUrl: string;
  phone?: string;
  year?: string;
  parentPhone?: string;
}

export interface AuthSession {
  user: User;
  wstoken: string;
}
