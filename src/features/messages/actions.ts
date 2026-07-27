"use server";

import { getSessionFromCookie } from "@/lib/session";
import { callMoodleRest } from "@/lib/moodle-server";
import { getConversationMessages } from "./server";
import type { ConversationThread, ChatMessage } from "./types";

export async function fetchThreadAction(
  convid: number,
): Promise<ConversationThread | null> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return null;
  return getConversationMessages(session.wstoken, session.user.id, convid);
}

export async function sendMessageAction(
  conversationid: number,
  text: string,
): Promise<{ ok: boolean; message?: ChatMessage; error?: string }> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { ok: false, error: "انتهت صلاحية الجلسة" };

  try {
    const result = await callMoodleRest<ChatMessage[]>({
      functionName: "core_message_send_messages_to_conversation",
      token: session.wstoken,
      params: {
        conversationid,
        "messages[0][text]": text,
        "messages[0][textformat]": 2,
      },
    });
    const msg = Array.isArray(result) ? result[0] : undefined;
    return { ok: true, message: msg };
  } catch (err) {
    const error = err instanceof Error ? err.message : "تعذّر إرسال الرسالة";
    return { ok: false, error };
  }
}

export async function markConversationReadAction(
  conversationid: number,
): Promise<void> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return;
  try {
    await callMoodleRest({
      functionName: "core_message_mark_all_conversation_messages_as_read",
      token: session.wstoken,
      params: { userid: session.user.id, conversationid },
    });
  } catch {
    // best-effort, ignore errors
  }
}
