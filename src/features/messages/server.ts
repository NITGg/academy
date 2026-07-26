import "server-only";
import { callMoodleRest } from "@/lib/moodle-server";
import type { Conversation } from "./types";

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
