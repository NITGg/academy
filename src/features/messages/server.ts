import "server-only";
import { callMoodleRest } from "@/lib/moodle-server";
import type { Conversation, ConversationThread } from "./types";

interface ConversationsResponse {
  conversations: Conversation[];
}

export async function getConversations(
  wstoken: string,
  userid: number,
): Promise<Conversation[]> {
  try {
    const data = await callMoodleRest<ConversationsResponse>({
      functionName: "core_message_get_conversations",
      token: wstoken,
      params: { userid, limitfrom: 0, limitnum: 50 },
    });
    return data?.conversations ?? [];
  } catch {
    return [];
  }
}

export async function getConversationMessages(
  wstoken: string,
  currentuserid: number,
  convid: number,
): Promise<ConversationThread | null> {
  try {
    const res = await callMoodleRest<ConversationThread>({
      functionName: "core_message_get_conversation_messages",
      token: wstoken,
      params: { currentuserid, convid, newest: 1, limitnum: 100 },
    });
    if (!res) return null;
    return {
      ...res,
      messages: (res.messages ?? []).slice().sort((a, b) => a.timecreated - b.timecreated || a.id - b.id),
    };
  } catch {
    return null;
  }
}

