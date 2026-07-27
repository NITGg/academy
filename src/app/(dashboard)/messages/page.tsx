import type { Metadata } from "next";
import { getSessionFromCookie } from "@/lib/session";
import { getConversations } from "@/features/messages/server";
import { MessagesClient } from "@/features/messages/components/MessagesClient";

export const metadata: Metadata = { title: "الرسائل" };

export default async function MessagesPage() {
  const session = await getSessionFromCookie();
  const conversations = session
    ? await getConversations(session.wstoken, session.user.id)
    : [];

  return (
    <MessagesClient
      conversations={conversations}
      currentUserId={session?.user.id ?? 0}
    />
  );
}
