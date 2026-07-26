import type { Metadata } from "next";
import { Bell } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { getNotifications } from "@/features/notifications/server";
import { cn } from "@/lib/utils";

export const metadata: Metadata = { title: "الإشعارات" };

function formatTime(ts: number): string {
  if (!ts) return "";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const hours = String(d.getHours()).padStart(2, "0");
  const minutes = String(d.getMinutes()).padStart(2, "0");
  return `${hours}:${minutes} ${d.getFullYear()}/${month}/${day}`;
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, "").replace(/&nbsp;/g, " ").trim();
}

export default async function NotificationsPage() {
  const session = await getSessionFromCookie();

  const notifications = session
    ? await getNotifications(session.wstoken, session.user.id)
    : [];

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Bell className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الإشعارات</h1>
        {notifications.length > 0 && (
          <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
            {notifications.filter((n) => !n.isRead).length || notifications.length}
          </span>
        )}
      </div>

      {notifications.length === 0 ? (
        <div className="flex min-h-[280px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
          <Bell className="size-10 opacity-20" />
          <p className="text-caption">لا توجد إشعارات</p>
        </div>
      ) : (
        <div className="space-y-2">
          {notifications.map((n) => {
            const body = n.smallmessage
              ? stripHtml(n.smallmessage)
              : stripHtml(n.text ?? "");

            return (
              <div
                key={n.id}
                className={cn(
                  "flex items-start gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm",
                  !n.isRead && "border-primary/20 bg-primary/5"
                )}
              >
                {/* Bell icon */}
                <div className="mt-0.5 shrink-0">
                  <Bell className={cn("size-5", n.isRead ? "text-muted-foreground/40" : "text-muted-foreground/60")} />
                </div>

                {/* Content */}
                <div className="min-w-0 flex-1 text-end">
                  <p className="text-small font-bold leading-snug text-foreground">{n.subject}</p>
                  {body && (
                    <p className="mt-1 text-[11px] leading-relaxed text-muted-foreground line-clamp-3">
                      {body}
                    </p>
                  )}
                  <p className="mt-1.5 text-[10px] text-muted-foreground/60">
                    {formatTime(n.timeCreated)}
                  </p>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
