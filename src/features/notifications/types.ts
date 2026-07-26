export interface AppNotification {
  id: number;
  subject: string;
  text: string;
  fullmessage?: string;
  smallmessage?: string;
  isRead: boolean;
  contexturl?: string;
  timeCreated: number;
  timeRead?: number;
  userFromFullName?: string;
  component?: string;
  eventType?: string;
}
