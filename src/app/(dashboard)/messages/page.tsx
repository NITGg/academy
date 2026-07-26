import type { Metadata } from "next";
import Image from "next/image";
import { MessageCircle } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { getConversations } from "@/features/messages/server";

export const metadata: Metadata = { title: "الرسائل" };

function formatTime(ts: number): string {
  if (!ts) return "";
  const d = new Date(ts * 1000);
  const now = new Date();
  const isToday =
    d.getDate() === now.getDate() &&
    d.getMonth() === now.getMonth() &&
    d.getFullYear() === now.getFullYear();

  if (isToday) {
    const h = String(d.getHours()).padStart(2, "0");
    const m = String(d.getMinutes()).padStart(2, "0");
    return `${h}:${m}`;
  }
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, "").replace(/&nbsp;/g, " ").trim();
}

export default async function MessagesPage() {
  const session = await getSessionFromCookie();
  const conversations = session
    ? await getConversations(session.wstoken, session.user.id)
    : [];

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <MessageCircle className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الرسائل</h1>
        {conversations.some((c) => (c.unreadcount ?? 0) > 0) && (
          <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
            {conversations.reduce((s, c) => s + (c.unreadcount ?? 0), 0)}
          </span>
        )}
      </div>

      {conversations.length === 0 ? (
        <div className="flex min-h-[280px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
          <MessageCircle className="size-10 opacity-20" />
          <p className="text-caption">لا توجد محادثات</p>
        </div>
      ) : (
        <div className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
          {conversations.map((conv) => {
            const other = conv.members.find((m) => m.id !== session?.user.id) ?? conv.members[0];
            const displayName = conv.name || other?.fullname || "محادثة";
            const avatar = conv.imageurl || other?.profileimageurl;
            const lastMsg = conv.messages[conv.messages.length - 1];
            const preview = lastMsg ? stripHtml(lastMsg.text) : "";
            const hasUnread = (conv.unreadcount ?? 0) > 0;

            return (
              <div key={conv.id} className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/30">
                {/* Avatar */}
                <div className="relative size-11 shrink-0 overflow-hidden rounded-full bg-muted border border-border">
                  {avatar ? (
                    <Image src={avatar} alt={displayName} fill sizes="44px" className="object-cover" />
                  ) : (
                    <span className="flex h-full w-full items-center justify-center text-base font-bold text-primary">
                      {displayName.charAt(0)}
                    </span>
                  )}
                  {other?.isonline && (
                    <span className="absolute bottom-0 end-0 size-2.5 rounded-full border-2 border-card bg-emerald-500" />
                  )}
                </div>

                {/* Text */}
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-[11px] text-muted-foreground">
                      {lastMsg ? formatTime(lastMsg.timecreated) : ""}
                    </span>
                    <span className={`truncate text-small font-bold ${hasUnread ? "text-foreground" : "text-foreground/80"}`}>
                      {displayName}
                    </span>
                  </div>
                  <div className="flex items-center justify-between gap-2 mt-0.5">
                    {hasUnread && (
                      <span className="shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">
                        {conv.unreadcount}
                      </span>
                    )}
                    {!hasUnread && <span />}
                    <p className="truncate text-[11px] text-muted-foreground">
                      {preview}
                    </p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
