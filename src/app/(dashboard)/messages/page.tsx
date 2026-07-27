import type { Metadata } from "next";
import { getSessionFromCookie } from "@/lib/session";
import { getConversations } from "@/features/messages/server";
import { getTeachers } from "@/features/teachers/server";
import { MessagesClient } from "@/features/messages/components/MessagesClient";

export const metadata: Metadata = { title: "الرسائل" };

interface Props {
  searchParams: Promise<{ convid?: string }>;
}

export default async function MessagesPage({ searchParams }: Props) {
  const { convid } = await searchParams;
  const initialConvid = convid ? parseInt(convid, 10) : undefined;

  const session = await getSessionFromCookie();

  const [conversations, teachersRes] = await Promise.all([
    session ? getConversations(session.wstoken, session.user.id) : [],
    getTeachers(),
  ]);

  return (
    <MessagesClient
      conversations={conversations}
      currentUserId={session?.user.id ?? 0}
      initialConvid={isNaN(Number(initialConvid)) ? undefined : initialConvid}
      teachers={teachersRes.teachers}
    />
  );
}
