"use client";

import { useState } from "react";
import { Bell, CheckCheck } from "lucide-react";
import { useLocale } from "next-intl";
import { cn } from "@/lib/utils";
import type { AppNotification } from "../types";

interface NotificationsPageClientProps {
  initialNotifications: AppNotification[];
}

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

export function NotificationsPageClient({
  initialNotifications,
}: NotificationsPageClientProps) {
  const locale = useLocale();
  const isAr = locale === "ar";

  const [notifications, setNotifications] =
    useState<AppNotification[]>(initialNotifications);
  const [loading, setLoading] = useState(false);

  const unreadCount = notifications.filter((n) => !n.isRead).length;

  async function handleMarkAllAsRead() {
    setLoading(true);
    try {
      await fetch("/api/notifications", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "mark_all_read" }),
      });
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, isRead: true }))
      );
    } catch {
      // ignore error
    } finally {
      setLoading(false);
    }
  }

  async function handleMarkAsRead(id: number) {
    try {
      await fetch("/api/notifications", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "mark_read", notificationId: id }),
      });
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, isRead: true } : n))
      );
    } catch {
      // ignore error
    }
  }

  return (
    <div className="space-y-4">
      {/* Header title & actions */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Bell className="size-6 text-primary" />
          <h1 className="text-h1 font-bold">
            {isAr ? "الإشعارات" : "Notifications"}
          </h1>
          {unreadCount > 0 && (
            <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
              {unreadCount}
            </span>
          )}
        </div>

        {unreadCount > 0 && (
          <button
            onClick={handleMarkAllAsRead}
            disabled={loading}
            className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-caption font-medium text-primary hover:bg-primary/10 transition-colors disabled:opacity-50"
          >
            <CheckCheck className="size-4" />
            <span>{isAr ? "تحديد الكل كمقروء" : "Mark all as read"}</span>
          </button>
        )}
      </div>

      {/* List */}
      {notifications.length === 0 ? (
        <div className="flex min-h-[280px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
          <Bell className="size-10 opacity-20" />
          <p className="text-caption">
            {isAr ? "لا توجد إشعارات" : "No notifications"}
          </p>
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
                onClick={() => !n.isRead && handleMarkAsRead(n.id)}
                className={cn(
                  "flex items-start gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm transition-colors",
                  !n.isRead &&
                    "border-primary/30 bg-primary/5 cursor-pointer hover:bg-primary/10"
                )}
              >
                {/* Bell icon */}
                <div className="mt-0.5 shrink-0">
                  <Bell
                    className={cn(
                      "size-5",
                      n.isRead ? "text-muted-foreground/40" : "text-primary"
                    )}
                  />
                </div>

                {/* Content */}
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-small font-bold leading-snug text-foreground">
                      {n.subject}
                    </p>
                    {!n.isRead && (
                      <span className="size-2 shrink-0 rounded-full bg-primary" />
                    )}
                  </div>
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
