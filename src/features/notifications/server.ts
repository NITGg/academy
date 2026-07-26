import "server-only";
import { callMoodleRest } from "@/lib/moodle-server";
import type { AppNotification } from "./types";

interface RawMessage {
  id: number;
  subject: string;
  text: string;
  fullmessage?: string;
  smallmessage?: string;
  contexturl?: string;
  timecreated: number;
  timeread?: number;
  userfromfullname?: string;
  component?: string;
  eventtype?: string;
}

interface MessagesResponse {
  messages: RawMessage[];
}

function mapMessage(m: RawMessage, isRead: boolean): AppNotification {
  return {
    id: m.id,
    subject: m.subject,
    text: m.text,
    fullmessage: m.fullmessage,
    smallmessage: m.smallmessage,
    isRead,
    contexturl: m.contexturl,
    timeCreated: m.timecreated,
    timeRead: m.timeread,
    userFromFullName: m.userfromfullname,
    component: m.component,
    eventType: m.eventtype,
  };
}

export async function getNotifications(
  wstoken: string,
  userid: number,
): Promise<AppNotification[]> {
  try {
    const [unread, read] = await Promise.allSettled([
      callMoodleRest<MessagesResponse>({
        functionName: "core_message_get_messages",
        token: wstoken,
        params: { useridto: userid, useridfrom: 0, type: "notifications", read: 0, limitnum: 50 },
      }),
      callMoodleRest<MessagesResponse>({
        functionName: "core_message_get_messages",
        token: wstoken,
        params: { useridto: userid, useridfrom: 0, type: "notifications", read: 1, limitnum: 50 },
      }),
    ]);

    const unreadList =
      unread.status === "fulfilled" ? (unread.value?.messages ?? []).map((m) => mapMessage(m, false)) : [];
    const readList =
      read.status === "fulfilled" ? (read.value?.messages ?? []).map((m) => mapMessage(m, true)) : [];

    return [...unreadList, ...readList].sort((a, b) => b.timeCreated - a.timeCreated);
  } catch {
    return [];
  }
}
